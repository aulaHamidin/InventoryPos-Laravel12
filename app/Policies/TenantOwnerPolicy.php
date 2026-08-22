<?php

namespace App\Policies;

use App\Enums\SubscriptionCapability;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ImpersonationContext;
use App\Support\SubscriptionCapabilityService;
use Illuminate\Database\Eloquent\Model;

abstract class TenantOwnerPolicy
{
    protected function owner(User $user, SubscriptionCapability $capability = SubscriptionCapability::Read): bool
    {
        return $user->role === UserRole::Owner
            && $user->is_active
            && $user->tenant?->canOperate() === true
            && app(SubscriptionCapabilityService::class)->allows($user, $capability);
    }

    protected function owns(User $user, Model $model, SubscriptionCapability $capability = SubscriptionCapability::Read): bool
    {
        return $this->owner($user, $capability) && (int) $model->getAttribute('tenant_id') === (int) $user->tenant_id;
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
        return ! ImpersonationContext::active() && $this->owner($user, SubscriptionCapability::Configure);
    }

    public function update(User $user, Model $model): bool
    {
        return ! ImpersonationContext::active() && $this->owns($user, $model, SubscriptionCapability::Configure);
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
