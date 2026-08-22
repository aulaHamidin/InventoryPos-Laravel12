<?php

namespace App\Policies;

use App\Enums\SubscriptionCapability;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CategoryPolicy extends TenantOwnerPolicy
{
    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Category && $this->owns($user, $model, SubscriptionCapability::Configure) && ! $model->items()->withTrashed()->exists();
    }
}
