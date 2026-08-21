<?php

namespace App\Actions\Pos;

use App\Actions\Audit\RecordAuditAction;
use App\Data\FinalizePosPaymentCommand;
use App\Enums\PosPaymentMethod;
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
use App\Support\Decimal;
use App\Support\PosActorGuard;
use App\Support\PosPendingExpiry;
use App\Support\PosRefundCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class FinalizePosTransactionAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(FinalizePosPaymentCommand $command): array
    {
        PosActorGuard::transaction($command->actor, $command->transactionId);

        $result = DB::transaction(function () use ($command): array {
            $transaction = PosTransaction::whereKey($command->transactionId)->lockForUpdate()->firstOrFail();

            if ($command->method->isManual()) {
                $existing = $this->existingManualPayment($command);
                if ($existing !== null) {
                    return $this->resultForExistingPayment($transaction, $existing);
                }
            }

            if ($transaction->status !== PosTransactionStatus::PendingPayment) {
                throw new ApiProblemException('Transaksi sudah diproses.', 'TRANSACTION_ALREADY_PROCESSED', 409);
            }

            if (PosPendingExpiry::isDue($transaction)) {
                $transaction->update(['status' => PosTransactionStatus::Expired]);
                $this->audit->execute(
                    'pos.expired',
                    null,
                    $transaction,
                    oldValues: ['status' => PosTransactionStatus::PendingPayment->value],
                    newValues: ['status' => PosTransactionStatus::Expired->value],
                    context: $command->auditContext,
                    metadata: ['source' => 'payment_guard'],
                );

                return ['outcome' => 'expired'];
            }

            $total = Decimal::money($transaction->total_amount);
            if ($command->method === PosPaymentMethod::Cash) {
                if ($command->cashReceived === null || Decimal::compare($command->cashReceived, $total) < 0) {
                    throw ValidationException::withMessages([
                        'cash_received' => ['Uang diterima kurang dari total transaksi.'],
                    ]);
                }
            }

            $payment = null;
            if ($command->method->isManual()) {
                $payment = $this->insertManualPayment($transaction, $command, $total);
                if ((int) $payment->pos_transaction_id !== (int) $transaction->getKey()) {
                    throw new ApiProblemException(
                        'Idempotency key telah digunakan untuk transaksi atau payload berbeda.',
                        'IDEMPOTENCY_CONFLICT',
                        409,
                    );
                }

                if ($payment->status !== PosPaymentStatus::Pending) {
                    return $this->resultForExistingPayment($transaction, $payment);
                }
            }

            $transactionLines = $transaction->items()->orderBy('item_id')->get();
            $itemIds = $transactionLines->pluck('item_id')->unique()->sort()->values()->all();
            $items = Item::whereIn('id', $itemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $insufficientItemId = null;

            foreach ($transactionLines as $line) {
                $item = $items->get($line->item_id);
                if ($item === null || ($item->stok_saat_ini < $line->qty && ! TenantContext::get()->allow_negative_stock)) {
                    $insufficientItemId = $line->item_id;
                    break;
                }
            }

            if ($insufficientItemId !== null) {
                if ($command->method === PosPaymentMethod::Cash) {
                    $transaction->update(['status' => PosTransactionStatus::Failed]);
                    $this->audit->execute(
                        'pos.payment_failed',
                        $command->actor,
                        $transaction,
                        oldValues: ['status' => PosTransactionStatus::PendingPayment->value],
                        newValues: ['status' => PosTransactionStatus::Failed->value, 'item_id' => $insufficientItemId],
                        context: $command->auditContext,
                    );

                    return ['outcome' => 'cash_stock_failed', 'item_id' => $insufficientItemId];
                }

                $payment->update(['status' => PosPaymentStatus::RefundRequired]);
                $transaction->update(['status' => PosTransactionStatus::RefundRequired]);
                $this->audit->execute(
                    'pos.manual_payment_refund_required',
                    $command->actor,
                    $transaction,
                    oldValues: ['status' => PosTransactionStatus::PendingPayment->value],
                    newValues: [
                        'status' => PosTransactionStatus::RefundRequired->value,
                        'payment_id' => $payment->getKey(),
                        'method' => $payment->method->value,
                        'amount' => $payment->amount,
                        'item_id' => $insufficientItemId,
                    ],
                    context: $command->auditContext,
                );

                return $this->paymentResult($transaction, $payment, requiresRefund: true, notifyRefund: true);
            }

            foreach ($transactionLines as $line) {
                $item = $items->get($line->item_id);
                $item->update(['stok_saat_ini' => $item->stok_saat_ini - $line->qty]);

                StockMovement::create([
                    'item_id' => $item->getKey(),
                    'user_id' => $command->actor->getKey(),
                    'movement_type' => 'sale',
                    'qty' => $line->qty,
                    'direction' => 'out',
                    'harga_satuan' => $item->average_cost,
                    'reference_type' => PosTransaction::class,
                    'reference_id' => $transaction->getKey(),
                    'note' => "Penjualan {$transaction->invoice_number}",
                ]);
            }

            if ($payment === null) {
                $payment = PosPayment::create([
                    'pos_transaction_id' => $transaction->getKey(),
                    'method' => PosPaymentMethod::Cash,
                    'amount' => $total,
                    'status' => PosPaymentStatus::Paid,
                    'idempotency_key' => 'cash-'.$transaction->getKey(),
                    'paid_at' => now(),
                ]);
            } else {
                $payment->update(['status' => PosPaymentStatus::Paid]);
            }

            $transaction->update(['status' => PosTransactionStatus::Completed, 'completed_at' => now()]);
            $change = $command->method === PosPaymentMethod::Cash
                ? Decimal::sub($command->cashReceived, $total)
                : null;

            $this->audit->execute(
                $command->method === PosPaymentMethod::Cash ? 'pos.paid_cash' : 'pos.paid_manual',
                $command->actor,
                $transaction,
                newValues: [
                    'payment_id' => $payment->getKey(),
                    'method' => $payment->method->value,
                    'amount' => $total,
                    'cash_received' => $command->cashReceived,
                    'change' => $change,
                    'manual_reference' => $command->manualReference,
                ],
                context: $command->auditContext,
            );
            ItemAnalyticsRecalculationRequested::dispatch(
                TenantContext::id(),
                array_map('intval', $itemIds),
                'sale',
            );

            return array_merge(
                $this->paymentResult($transaction, $payment),
                ['cash_received' => $command->cashReceived, 'change' => $change],
            );
        }, 3);

        if (($result['outcome'] ?? null) === 'expired') {
            throw new ApiProblemException(
                'Checkout telah kedaluwarsa. Buat checkout baru untuk menggunakan harga terbaru.',
                'TRANSACTION_EXPIRED',
                409,
            );
        }

        if (($result['outcome'] ?? null) === 'cash_stock_failed') {
            throw new ApiProblemException(
                "Stok item {$result['item_id']} tidak mencukupi saat pembayaran.",
                'INSUFFICIENT_STOCK',
                422,
            );
        }

        if (($result['notify_refund'] ?? false) === true) {
            $owners = User::query()->where('role', UserRole::Owner->value)->get();
            Notification::send($owners, new PosRefundRequired(
                $result['transaction']->getKey(),
                $result['transaction']->invoice_number,
                $result['refund_due_amount'],
            ));
        }

        $result['transaction'] = $result['transaction']->fresh(['items', 'payment.confirmedBy']);
        $result['payment'] = $result['payment']->fresh('confirmedBy');

        return $result;
    }

    private function existingManualPayment(FinalizePosPaymentCommand $command): ?PosPayment
    {
        $payment = PosPayment::where('idempotency_key', $command->idempotencyKey)->lockForUpdate()->first();
        if ($payment === null) {
            return null;
        }

        $this->assertCanonicalPayment($payment, $command);

        return $payment;
    }

    private function insertManualPayment(
        PosTransaction $transaction,
        FinalizePosPaymentCommand $command,
        string $total,
    ): PosPayment {
        try {
            return PosPayment::create([
                'pos_transaction_id' => $transaction->getKey(),
                'method' => $command->method,
                'amount' => $total,
                'status' => PosPaymentStatus::Pending,
                'confirmed_by' => $command->actor->getKey(),
                'manual_reference' => $command->manualReference,
                'confirmation_note' => $command->confirmationNote,
                'idempotency_key' => $command->idempotencyKey,
                'paid_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKey($exception)) {
                throw $exception;
            }

            $existing = PosPayment::where('idempotency_key', $command->idempotencyKey)->first();
            if ($existing === null) {
                throw $exception;
            }

            $this->assertCanonicalPayment($existing, $command);

            return $existing;
        }
    }

    private function assertCanonicalPayment(PosPayment $payment, FinalizePosPaymentCommand $command): void
    {
        $identical = (int) $payment->pos_transaction_id === $command->transactionId
            && (int) $payment->confirmed_by === (int) $command->actor->getKey()
            && $payment->method === $command->method
            && $this->normalize($payment->manual_reference) === $command->manualReference
            && $this->normalize($payment->confirmation_note) === $command->confirmationNote;

        if (! $identical) {
            throw new ApiProblemException(
                'Idempotency key telah digunakan untuk transaksi atau payload berbeda.',
                'IDEMPOTENCY_CONFLICT',
                409,
            );
        }
    }

    private function resultForExistingPayment(PosTransaction $transaction, PosPayment $payment): array
    {
        if ($payment->status === PosPaymentStatus::Pending) {
            throw new ApiProblemException('Konfirmasi pembayaran sedang diproses.', 'PAYMENT_IN_PROGRESS', 409);
        }

        return $this->paymentResult(
            $transaction,
            $payment,
            requiresRefund: $payment->status === PosPaymentStatus::RefundRequired,
        );
    }

    private function paymentResult(
        PosTransaction $transaction,
        PosPayment $payment,
        bool $requiresRefund = false,
        bool $notifyRefund = false,
    ): array {
        $payment->setRelation('transaction', $transaction->loadMissing('items'));
        $obligation = PosRefundCalculator::obligation($payment);

        return [
            'outcome' => 'payment_recorded',
            'transaction' => $transaction,
            'payment' => $payment,
            'requires_refund' => $requiresRefund,
            'refund_obligation_amount' => $obligation,
            'refund_due_amount' => PosRefundCalculator::due($payment),
            'notify_refund' => $notifyRefund,
        ];
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    private function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
