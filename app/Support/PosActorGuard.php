<?php

namespace App\Support;

use App\Enums\SubscriptionCapability;
use App\Enums\UserRole;
use App\Models\PosPayment;
use App\Models\PosTransaction;
use App\Models\User;
use App\Services\ImpersonationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PosActorGuard
{
    public static function assertOperator(User $actor): User
    {
        if (ImpersonationContext::active()) {
            throw new AuthorizationException;
        }
        /** @var User $persistedActor */
        $persistedActor = OwnershipGuard::validate(User::class, $actor->getKey());
        if (! $persistedActor->is_active
            || ! in_array($persistedActor->role, [UserRole::Owner, UserRole::Staff], true)
            || $persistedActor->tenant?->canOperate() !== true
            || ! app(SubscriptionCapabilityService::class)->allows($persistedActor, SubscriptionCapability::Operate)) {
            throw new AuthorizationException;
        }

        return $persistedActor;
    }

    public static function transaction(User $actor, int $transactionId): PosTransaction
    {
        $persistedActor = self::assertOperator($actor);
        /** @var PosTransaction $transaction */
        $transaction = OwnershipGuard::validate(PosTransaction::class, $transactionId);
        if ($persistedActor->role === UserRole::Staff
            && (int) $transaction->cashier_id !== (int) $persistedActor->getKey()) {
            throw (new ModelNotFoundException)->setModel(PosTransaction::class, [$transactionId]);
        }

        return $transaction;
    }

    public static function payment(User $actor, int $paymentId): PosPayment
    {
        $persistedActor = self::assertOperator($actor);
        /** @var PosPayment $payment */
        $payment = OwnershipGuard::validate(PosPayment::class, $paymentId);
        $transaction = PosTransaction::whereKey($payment->pos_transaction_id)->firstOrFail();
        if ($persistedActor->role === UserRole::Staff
            && (int) $transaction->cashier_id !== (int) $persistedActor->getKey()) {
            throw (new ModelNotFoundException)->setModel(PosPayment::class, [$paymentId]);
        }

        return $payment;
    }
}
