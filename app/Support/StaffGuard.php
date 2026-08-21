<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StaffGuard
{
    public static function assertOwner(User $actor): void
    {
        OwnerActorGuard::assert($actor);
    }

    public static function target(int $staffId): User
    {
        /** @var User $staff */
        $staff = OwnershipGuard::validate(User::class, $staffId);
        if ($staff->trashed()) {
            throw (new ModelNotFoundException)->setModel(User::class, [$staffId]);
        }
        if ($staff->role !== UserRole::Staff) {
            throw new AuthorizationException;
        }

        return $staff;
    }
}
