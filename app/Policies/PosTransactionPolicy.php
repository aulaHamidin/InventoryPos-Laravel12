<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PosTransactionPolicy extends TenantOwnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->operator($user);
    }

    public function view(User $user, Model $model): bool
    {
        return ($this->owner($user) && (int) $model->tenant_id === (int) $user->tenant_id)
            || $this->staffOwns($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->operator($user);
    }

    public function pay(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }

    public function void(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function return(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    private function operator(User $user): bool
    {
        return $user->is_active
            && in_array($user->role, [UserRole::Owner, UserRole::Staff], true)
            && $user->tenant?->canOperate() === true;
    }

    private function staffOwns(User $user, Model $model): bool
    {
        return $user->role === UserRole::Staff
            && $this->operator($user)
            && (int) $model->tenant_id === (int) $user->tenant_id
            && (int) $model->cashier_id === (int) $user->getKey();
    }
}
