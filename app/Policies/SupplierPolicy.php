<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SupplierPolicy extends TenantOwnerPolicy
{
    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Supplier && $this->owns($user, $model) && ! $model->itemSupplierLinks()->exists();
    }
}
