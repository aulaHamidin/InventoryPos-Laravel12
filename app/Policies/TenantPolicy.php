<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->role === UserRole::Owner && $user->tenant_id === $tenant->getKey();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->view($user, $tenant);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return false;
    }
}
