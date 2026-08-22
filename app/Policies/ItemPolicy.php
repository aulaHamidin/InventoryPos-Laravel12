<?php

namespace App\Policies;

use App\Enums\SubscriptionCapability;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\SubscriptionCapabilityService;
use Illuminate\Database\Eloquent\Model;

class ItemPolicy extends TenantOwnerPolicy
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

    private function activeStaff(User $user): bool
    {
        return $user->role === UserRole::Staff && $user->is_active && $user->tenant?->canOperate() === true
            && app(SubscriptionCapabilityService::class)->allows($user, SubscriptionCapability::Read);
    }
}
