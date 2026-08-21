<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class TenantOwnerPolicy
{
    protected function owner(User $user): bool
    {
        return $user->role === UserRole::Owner
            && $user->is_active
            && $user->tenant?->canOperate() === true;
    }

    protected function owns(User $user, Model $model): bool
    {
        return $this->owner($user) && (int) $model->getAttribute('tenant_id') === (int) $user->tenant_id;
    }

    public function viewAny(User $user): bool
    {
        return $this->owner($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->owner($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function restore(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }
}
