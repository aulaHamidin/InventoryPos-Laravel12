<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class OwnerActorGuard
{
    public static function assert(User $actor): void
    {
        /** @var User $persistedActor */
        $persistedActor = OwnershipGuard::validate(User::class, $actor->getKey());
        if ($persistedActor->role !== UserRole::Owner
            || ! $persistedActor->is_active
            || $persistedActor->tenant?->canOperate() !== true) {
            throw new AuthorizationException;
        }
    }
}
