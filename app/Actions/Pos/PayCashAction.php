<?php

namespace App\Actions\Pos;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\PosPaymentMethod;
use App\Enums\PosPaymentStatus;
use App\Enums\PosTransactionStatus;
use App\Exceptions\ApiProblemException;
use App\Models\Item;
use App\Models\PosPayment;
use App\Models\PosTransaction;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\Decimal;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayCashAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $transactionId, int|float|string $cashReceived, User $actor, ?AuditContext $context = null): array
    {
        OwnershipGuard::validate(User::class, $actor->getKey());
        OwnershipGuard::validate(PosTransaction::class, $transactionId);

        try {
            $cash = Decimal::money($cashReceived);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['cash_received' => ['Nilai uang diterima tidak valid.']]);
        }

        $result = DB::transaction(function () use ($transactionId, $cash, $actor, $context): array {
            $transaction = PosTransaction::whereKey($transactionId)->lockForUpdate()->firstOrFail();

            if ($transaction->status !== PosTransactionStatus::PendingPayment) {
                throw new ApiProblemException('Transaksi sudah diproses.', 'TRANSACTION_ALREADY_PROCESSED', 409);
            }

            $total = Decimal::money($transaction->total_amount);
            if (Decimal::compare($cash, $total) < 0) {
                throw ValidationException::withMessages(['cash_received' => ['Uang diterima kurang dari total transaksi.']]);
            }

            $transactionLines = $transaction->items()->orderBy('item_id')->get();
            $itemIds = $transactionLines->pluck('item_id')->unique()->sort()->values()->all();
            $items = Item::whereIn('id', $itemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($transactionLines as $line) {
                $item = $items->get($line->item_id);
                if ($item === null || ($item->stok_saat_ini < $line->qty && ! TenantContext::get()->allow_negative_stock)) {
                    $transaction->update(['status' => PosTransactionStatus::Failed]);
                    $this->audit->execute(
                        'pos.payment_failed',
                        $actor,
                        $transaction,
                        oldValues: ['status' => PosTransactionStatus::PendingPayment->value],
                        newValues: ['status' => PosTransactionStatus::Failed->value, 'item_id' => $line->item_id],
                        context: $context,
                    );

                    return [
                        'failed' => true,
                        'message' => "Stok item {$line->item_id} tidak mencukupi saat pembayaran.",
                    ];
                }
            }

            foreach ($transactionLines as $line) {
                $item = $items->get($line->item_id);
                $item->update(['stok_saat_ini' => $item->stok_saat_ini - $line->qty]);

                StockMovement::create([
                    'item_id' => $item->getKey(),
                    'user_id' => $actor->getKey(),
                    'movement_type' => 'sale',
                    'qty' => $line->qty,
                    'direction' => 'out',
                    'harga_satuan' => $item->average_cost,
                    'reference_type' => PosTransaction::class,
                    'reference_id' => $transaction->getKey(),
                    'note' => "Penjualan {$transaction->invoice_number}",
                ]);
            }

            $payment = PosPayment::create([
                'pos_transaction_id' => $transaction->getKey(),
                'method' => PosPaymentMethod::Cash,
                'amount' => $total,
                'status' => PosPaymentStatus::Paid,
                'idempotency_key' => 'cash-'.$transaction->getKey(),
                'paid_at' => now(),
            ]);

            $transaction->update(['status' => PosTransactionStatus::Completed, 'completed_at' => now()]);
            $change = Decimal::sub($cash, $total);

            $this->audit->execute(
                'pos.paid_cash',
                $actor,
                $transaction,
                newValues: ['amount' => $total, 'cash_received' => $cash, 'change' => $change],
                context: $context,
            );

            return [
                'failed' => false,
                'transaction' => $transaction,
                'payment' => $payment,
                'cash_received' => $cash,
                'change' => $change,
            ];
        }, 3);

        if ($result['failed']) {
            throw new ApiProblemException($result['message'], 'INSUFFICIENT_STOCK', 422);
        }

        $result['transaction'] = $result['transaction']->fresh(['items', 'payments']);

        return $result;
    }
}
