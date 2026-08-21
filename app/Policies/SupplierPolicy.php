<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SupplierPolicy extends TenantOwnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->owner($user) || $this->activeStaff($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->owns($user, $model)
            || ($this->activeStaff($user) && (int) $model->getAttribute('tenant_id') === (int) $user->tenant_id);
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Supplier && $this->owns($user, $model) && ! $model->itemSupplierLinks()->exists();
    }

    private function activeStaff(User $user): bool
    {
        return $user->role === UserRole::Staff && $user->is_active && $user->tenant?->canOperate() === true;
    }
}
