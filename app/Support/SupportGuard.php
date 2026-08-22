<?php

namespace App\Support;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Auth\Access\AuthorizationException;

final class SupportGuard
{
    public static function target(Admin|int $support): Admin
    {
        $target = Admin::query()->findOrFail($support instanceof Admin ? $support->getKey() : $support);
        if ($target->role !== AdminRole::Support) {
            throw new AuthorizationException;
        }

        return $target;
    }
}
