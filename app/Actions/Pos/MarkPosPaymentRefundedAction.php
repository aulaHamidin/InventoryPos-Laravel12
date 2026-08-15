<?php

namespace App\Actions\Pos;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\PosPaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiProblemException;
use App\Models\PosPayment;
use App\Models\PosTransaction;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\Decimal;
use App\Support\OwnershipGuard;
use App\Support\PosRefundCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkPosPaymentRefundedAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(
        int $paymentId,
        int|float|string $refundedAmount,
        string $note,
        User $actor,
        ?AuditContext $context = null,
    ): array {
        $this->assertOwner($actor);
        /** @var PosPayment $payment */
        $payment = OwnershipGuard::validate(PosPayment::class, $paymentId);
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 1000) {
            throw ValidationException::withMessages(['note' => ['Catatan wajib dan maksimal 1000 karakter.']]);
        }
        try {
            $target = Decimal::money($refundedAmount);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['refunded_amount' => ['Nilai refund tidak valid.']]);
        }

        return DB::transaction(function () use ($payment, $target, $note, $actor, $context): array {
            $transaction = PosTransaction::whereKey($payment->pos_transaction_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = PosPayment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $transaction->load('items');
            $lockedPayment->setRelation('transaction', $transaction);
            $obligation = PosRefundCalculator::obligation($lockedPayment);
            $current = Decimal::money($lockedPayment->refunded_amount);

            if ($lockedPayment->status === PosPaymentStatus::Refunded && Decimal::compare($target, $current) === 0) {
                return [
                    'payment' => $lockedPayment,
                    'transaction' => $transaction,
                    'refund_obligation_amount' => $obligation,
                    'refund_due_amount' => PosRefundCalculator::due($lockedPayment),
                    'no_op' => true,
                ];
            }

            if (! in_array($lockedPayment->status, [PosPaymentStatus::RefundRequired, PosPaymentStatus::PartiallyRefunded], true)) {
                throw new ApiProblemException(
                    'Payment tidak memiliki kewajiban refund terbuka.',
                    'INVALID_STATE_TRANSITION',
                    409,
                );
            }

            if (Decimal::compare($target, $current) < 0) {
                throw new ApiProblemException('Nilai cumulative refund tidak boleh turun.', 'REFUND_AMOUNT_DECREASED', 422);
            }
            if (Decimal::compare($target, $obligation) > 0 || Decimal::compare($target, (string) $lockedPayment->amount) > 0) {
                throw new ApiProblemException('Nilai refund melebihi kewajiban refund.', 'REFUND_AMOUNT_EXCEEDED', 422);
            }

            if (Decimal::compare($target, $current) === 0) {
                return [
                    'payment' => $lockedPayment,
                    'transaction' => $transaction,
                    'refund_obligation_amount' => $obligation,
                    'refund_due_amount' => PosRefundCalculator::due($lockedPayment),
                    'no_op' => true,
                ];
            }

            $newStatus = Decimal::compare($target, (string) $lockedPayment->amount) === 0
                ? PosPaymentStatus::Refunded
                : PosPaymentStatus::PartiallyRefunded;
            $lockedPayment->update([
                'refunded_amount' => $target,
                'status' => $newStatus,
                'refunded_by' => $actor->getKey(),
                'refunded_at' => now(),
            ]);

            $this->audit->execute(
                'pos.refund_recorded',
                $actor,
                $lockedPayment,
                oldValues: ['refunded_amount' => $current],
                newValues: [
                    'refunded_amount' => $target,
                    'status' => $newStatus->value,
                    'note' => $note,
                    'refund_obligation_amount' => $obligation,
                ],
                context: $context,
            );

            $lockedPayment->refresh()->setRelation('transaction', $transaction);

            return [
                'payment' => $lockedPayment,
                'transaction' => $transaction,
                'refund_obligation_amount' => $obligation,
                'refund_due_amount' => PosRefundCalculator::due($lockedPayment),
                'no_op' => false,
            ];
        }, 3);
    }

    private function assertOwner(User $actor): void
    {
        OwnershipGuard::validate(User::class, $actor->getKey());
        if ($actor->role !== UserRole::Owner) {
            throw new AuthorizationException;
        }
    }
}
