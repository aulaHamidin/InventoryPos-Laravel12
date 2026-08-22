<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ImpersonationContext;
use Illuminate\Auth\Access\AuthorizationException;

final class DeletionActorGuard
{
    public static function owner(User $actor): User
    {
        if (ImpersonationContext::active()) {
            throw new AuthorizationException;
        }

        $persisted = User::query()->withoutGlobalScopes()->find($actor->getKey());
        if ($persisted === null || ! $persisted->is_active || $persisted->role !== UserRole::Owner) {
            throw new AuthorizationException;
        }

        return $persisted;
    }
}
