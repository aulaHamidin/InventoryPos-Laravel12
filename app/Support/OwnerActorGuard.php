<?php

namespace App\Support;

use App\Enums\SubscriptionCapability;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ImpersonationContext;
use Illuminate\Auth\Access\AuthorizationException;

final class OwnerActorGuard
{
    public static function assert(User $actor, SubscriptionCapability $capability = SubscriptionCapability::Configure): void
    {
        if (ImpersonationContext::active()) {
            throw new AuthorizationException;
        }
        /** @var User $persistedActor */
        $persistedActor = OwnershipGuard::validate(User::class, $actor->getKey());
        if ($persistedActor->role !== UserRole::Owner
            || ! $persistedActor->is_active
            || $persistedActor->tenant?->canOperate() !== true
            || ! app(SubscriptionCapabilityService::class)->allows($persistedActor, $capability)) {
            throw new AuthorizationException;
        }
    }
}
