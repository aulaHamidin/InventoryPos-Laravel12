<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Trait HasTenantContext
 *
 * Helper for testing tenant-scoped features.
 */
trait HasTenantContext
{
    protected ?Tenant $currentTenant = null;

    protected ?User $currentUser = null;

    /**
     * Create a tenant and owner, and act as that owner.
     */
    protected function setupTenantContext(): void
    {
        $this->currentTenant = Tenant::factory()->create();

        $this->currentUser = User::factory()->create([
            'tenant_id' => $this->currentTenant->id,
            'role' => UserRole::Owner,
        ]);

        Sanctum::actingAs($this->currentUser, ['*']);

        // For session-based tests
        $this->actingAs($this->currentUser);
    }
}
