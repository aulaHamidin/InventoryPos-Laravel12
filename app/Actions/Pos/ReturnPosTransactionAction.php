<?php

namespace App\Actions\Pos;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\PosPaymentStatus;
use App\Enums\PosTransactionStatus;
use App\Enums\UserRole;
use App\Events\ItemAnalyticsRecalculationRequested;
use App\Exceptions\ApiProblemException;
use App\Models\Item;
use App\Models\PosPayment;
use App\Models\PosTransaction;
use App\Models\PosTransactionItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\PosRefundRequired;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\Decimal;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use App\Support\PosRefundCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ReturnPosTransactionAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $transactionId, array $requestedLines, User $actor, ?AuditContext $context = null): array
    {
        $this->assertOwner($actor);
        OwnershipGuard::validate(PosTransaction::class, $transactionId);
        $quantities = $this->canonicalQuantities($requestedLines);

        $result = DB::transaction(function () use ($transactionId, $quantities, $actor, $context): array {
            $transaction = PosTransaction::whereKey($transactionId)->lockForUpdate()->firstOrFail();
            $payments = $transaction->payments()->orderBy('id')->lockForUpdate()->get();

            if (! in_array($transaction->status, [PosTransactionStatus::Completed, PosTransactionStatus::PartiallyReturned], true)) {
                throw new ApiProblemException(
                    'Retur hanya dapat dilakukan pada transaksi selesai atau retur sebagian.',
                    'INVALID_STATE_TRANSITION',
                    409,
                );
            }

            $lines = $transaction->items()->orderBy('item_id')->lockForUpdate()->get();
            $linesById = $lines->keyBy('id');
            foreach ($quantities as $lineId => $qty) {
                $line = $linesById->get($lineId);
                if ($line === null) {
                    throw new ApiProblemException('Baris retur tidak berasal dari transaksi ini.', 'NOT_FOUND', 404);
                }
                if ($line->returned_qty + $qty > $line->qty) {
                    throw ValidationException::withMessages([
                        'items' => ["Jumlah retur baris {$lineId} melebihi jumlah terjual."],
                    ]);
                }
            }

            $itemIds = $lines->whereIn('id', array_keys($quantities))->pluck('item_id')->unique()->sort()->values()->all();
            $items = Item::whereIn('id', $itemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $refundDelta = '0.00';

            foreach ($quantities as $lineId => $qty) {
                $line = $linesById->get($lineId);
                $item = $items->get($line->item_id);
                if ($item === null) {
                    throw new ApiProblemException('Item retur tidak ditemukan.', 'INVALID_STATE_TRANSITION', 409);
                }

                $oldTarget = PosRefundCalculator::lineTarget($line);
                $newReturnedQty = $line->returned_qty + $qty;
                $newTarget = PosRefundCalculator::lineTarget($line, $newReturnedQty);
                $refundDelta = Decimal::add(
                    $refundDelta,
                    Decimal::sub($newTarget, $oldTarget),
                );

                $line->update(['returned_qty' => $newReturnedQty]);
                $item->update(['stok_saat_ini' => $item->stok_saat_ini + $qty]);
                StockMovement::create([
                    'item_id' => $item->getKey(),
                    'user_id' => $actor->getKey(),
                    'movement_type' => 'customer_return',
                    'qty' => $qty,
                    'direction' => 'in',
                    'harga_satuan' => $item->average_cost,
                    'reference_type' => PosTransaction::class,
                    'reference_id' => $transaction->getKey(),
                    'note' => "Retur {$transaction->invoice_number}; line {$lineId}",
                ]);
            }

            $transaction->load('items');
            $fullyReturned = $transaction->items->every(fn ($line): bool => $line->returned_qty === $line->qty);
            $oldStatus = $transaction->status;
            $transaction->update([
                'status' => $fullyReturned
                    ? PosTransactionStatus::FullyReturned
                    : PosTransactionStatus::PartiallyReturned,
            ]);

            foreach ($payments as $payment) {
                if ($payment->status === PosPaymentStatus::Paid) {
                    $payment->update(['status' => PosPaymentStatus::RefundRequired]);
                }
            }

            /** @var PosPayment|null $payment */
            $payment = $payments->last()?->fresh();
            if ($payment === null) {
                throw new ApiProblemException('Payment transaksi tidak ditemukan.', 'INVALID_STATE_TRANSITION', 409);
            }
            $transaction->refresh()->load('items');
            $payment->setRelation('transaction', $transaction);
            $obligation = PosRefundCalculator::obligation($payment);
            $due = PosRefundCalculator::due($payment);

            $this->audit->execute(
                'pos.returned',
                $actor,
                $transaction,
                oldValues: ['status' => $oldStatus->value],
                newValues: [
                    'status' => $transaction->status->value,
                    'lines' => $quantities,
                    'refund_delta' => $refundDelta,
                    'refund_obligation_amount' => $obligation,
                    'refund_due_amount' => $due,
                ],
                context: $context,
            );
            ItemAnalyticsRecalculationRequested::dispatch(
                TenantContext::id(),
                array_map('intval', $itemIds),
                'customer_return',
            );

            return [
                'transaction' => $transaction,
                'payment' => $payment,
                'refund_delta_amount' => $refundDelta,
                'refund_obligation_amount' => $obligation,
                'refund_due_amount' => $due,
            ];
        }, 3);

        if ($result['refund_due_amount'] !== '0.00') {
            $owners = User::query()->where('role', UserRole::Owner->value)->get();
            Notification::send($owners, new PosRefundRequired(
                $result['transaction']->getKey(),
                $result['transaction']->invoice_number,
                $result['refund_due_amount'],
            ));
        }

        return $result;
    }

    private function canonicalQuantities(array $requestedLines): array
    {
        if ($requestedLines === []) {
            throw ValidationException::withMessages(['items' => ['Minimal satu baris retur wajib diisi.']]);
        }

        $quantities = [];
        foreach ($requestedLines as $line) {
            $lineId = (int) ($line['pos_transaction_item_id'] ?? 0);
            $qty = (int) ($line['qty'] ?? 0);
            if ($lineId <= 0 || $qty <= 0 || isset($quantities[$lineId])) {
                throw ValidationException::withMessages([
                    'items' => ['Setiap baris retur harus unik dan memiliki quantity positif.'],
                ]);
            }
            OwnershipGuard::validate(PosTransactionItem::class, $lineId);
            $quantities[$lineId] = $qty;
        }
        ksort($quantities, SORT_NUMERIC);

        return $quantities;
    }

    private function assertOwner(User $actor): void
    {
        OwnerActorGuard::assert($actor);
    }
}
