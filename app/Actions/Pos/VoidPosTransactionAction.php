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
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\PosRefundRequired;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use App\Support\PosRefundCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class VoidPosTransactionAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $transactionId, string $reason, User $actor, ?AuditContext $context = null): array
    {
        $this->assertOwner($actor);
        OwnershipGuard::validate(PosTransaction::class, $transactionId);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => ['Alasan wajib dan maksimal 1000 karakter.']]);
        }

        $result = DB::transaction(function () use ($transactionId, $reason, $actor, $context): array {
            $transaction = PosTransaction::whereKey($transactionId)->lockForUpdate()->firstOrFail();
            $payments = $transaction->payments()->orderBy('id')->lockForUpdate()->get();
            $lines = $transaction->items()->orderBy('item_id')->get();

            if ($transaction->status !== PosTransactionStatus::Completed || $lines->contains('returned_qty', '>', 0)) {
                throw new ApiProblemException(
                    'Void hanya dapat dilakukan pada transaksi selesai yang belum pernah diretur.',
                    'INVALID_STATE_TRANSITION',
                    409,
                );
            }

            $itemIds = $lines->pluck('item_id')->unique()->sort()->values()->all();
            $items = Item::whereIn('id', $itemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($lines as $line) {
                $item = $items->get($line->item_id);
                if ($item === null) {
                    throw new ApiProblemException('Item transaksi tidak ditemukan.', 'INVALID_STATE_TRANSITION', 409);
                }

                $item->update(['stok_saat_ini' => $item->stok_saat_ini + $line->qty]);
                StockMovement::create([
                    'item_id' => $item->getKey(),
                    'user_id' => $actor->getKey(),
                    'movement_type' => 'sale_void',
                    'qty' => $line->qty,
                    'direction' => 'in',
                    'harga_satuan' => $item->average_cost,
                    'reference_type' => PosTransaction::class,
                    'reference_id' => $transaction->getKey(),
                    'note' => $reason,
                ]);
            }

            foreach ($payments as $payment) {
                if ($payment->status === PosPaymentStatus::Paid) {
                    $payment->update(['status' => PosPaymentStatus::RefundRequired]);
                }
            }

            $transaction->update(['status' => PosTransactionStatus::Voided]);
            $this->audit->execute(
                'pos.voided',
                $actor,
                $transaction,
                oldValues: ['status' => PosTransactionStatus::Completed->value],
                newValues: ['status' => PosTransactionStatus::Voided->value, 'reason' => $reason],
                context: $context,
            );
            ItemAnalyticsRecalculationRequested::dispatch(
                TenantContext::id(),
                array_map('intval', $itemIds),
                'sale_void',
            );

            /** @var PosPayment|null $payment */
            $payment = $payments->last()?->fresh();
            if ($payment === null) {
                throw new ApiProblemException('Payment transaksi tidak ditemukan.', 'INVALID_STATE_TRANSITION', 409);
            }
            $transaction->load('items');
            $payment->setRelation('transaction', $transaction);

            return [
                'transaction' => $transaction,
                'payment' => $payment,
                'refund_obligation_amount' => PosRefundCalculator::obligation($payment),
                'refund_due_amount' => PosRefundCalculator::due($payment),
            ];
        }, 3);

        $owners = User::query()->where('role', UserRole::Owner->value)->get();
        Notification::send($owners, new PosRefundRequired(
            $result['transaction']->getKey(),
            $result['transaction']->invoice_number,
            $result['refund_due_amount'],
        ));

        return $result;
    }

    private function assertOwner(User $actor): void
    {
        OwnerActorGuard::assert($actor);
    }
}
