<?php

namespace App\Policies;

use App\Enums\SubscriptionCapability;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StockOpnamePolicy extends TenantOwnerPolicy
{
    public function create(User $user): bool
    {
        return $this->owner($user, SubscriptionCapability::Operate);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model, SubscriptionCapability::Operate);
    }
}
