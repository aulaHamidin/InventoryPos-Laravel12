<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StockMovementPolicy extends TenantOwnerPolicy
{
    public function update(User $user, Model $model): bool
    {
        return false;
    }
}
