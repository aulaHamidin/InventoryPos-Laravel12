<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->owner($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->owner($user)
            && $model->role === UserRole::Staff
            && $user->tenant_id === $model->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->owner($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->view($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    private function owner(User $user): bool
    {
        return $user->role === UserRole::Owner && $user->is_active && $user->tenant?->canOperate() === true;
    }
}
