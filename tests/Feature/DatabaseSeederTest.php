<?php

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;

it('seeds an Owner and Staff in the same demo tenant', function () {
    $this->seed();

    TenantContext::clear();

    $tenant = Tenant::where('slug', 'demo-toko')->sole();
    $users = User::withoutGlobalScopes()
        ->where('tenant_id', $tenant->getKey())
        ->get()
        ->keyBy('email');

    expect($users)->toHaveCount(2)
        ->and($users['owner@demo.com']->role)->toBe(UserRole::Owner)
        ->and($users['staff@demo.com']->role)->toBe(UserRole::Staff)
        ->and(Subscription::query()->where('tenant_id', $tenant->id)->where('status', SubscriptionStatus::Active)->whereHas('plan', fn ($query) => $query->where('code', 'LEGACY-F0-F9'))->count())->toBe(1);
});
