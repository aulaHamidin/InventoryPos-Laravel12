<?php

namespace App\Support;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Services\ImpersonationContext;
use Illuminate\Auth\Access\AuthorizationException;

final class AdminActorGuard
{
    public static function active(Admin $actor): void
    {
        if (ImpersonationContext::active()) {
            throw new AuthorizationException;
        }

        $persisted = Admin::query()->find($actor->getKey());
        if ($persisted === null || ! $persisted->is_active) {
            throw new AuthorizationException;
        }
    }

    public static function superAdmin(Admin $actor): void
    {
        self::active($actor);
        if ($actor->role !== AdminRole::SuperAdmin) {
            throw new AuthorizationException;
        }
    }
}
