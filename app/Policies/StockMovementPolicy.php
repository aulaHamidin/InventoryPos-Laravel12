<?php

namespace App\Policies;

use App\Enums\SubscriptionCapability;
use App\Models\User;
use App\Services\ImpersonationContext;
use Illuminate\Database\Eloquent\Model;

class StockMovementPolicy extends TenantOwnerPolicy
{
    public function viewAny(User $user): bool
    {
        return ! ImpersonationContext::isSupport() && parent::viewAny($user);
    }

    public function view(User $user, Model $model): bool
    {
        return ! ImpersonationContext::isSupport() && parent::view($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->owner($user, SubscriptionCapability::Operate);
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }
}
