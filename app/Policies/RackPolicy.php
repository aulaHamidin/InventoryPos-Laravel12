<?php

namespace App\Policies;

use App\Enums\SubscriptionCapability;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RackPolicy extends TenantOwnerPolicy
{
    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Rack && $this->owns($user, $model, SubscriptionCapability::Configure) && ! $model->items()->withTrashed()->exists();
    }
}
