<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait AssertsTenantIsolation
 *
 * Helper to test that a model instance is properly isolated to its tenant.
 */
trait AssertsTenantIsolation
{
    /**
     * Assert that querying a model class with tenant isolation works correctly.
     * Expects that retrieving all records only returns the records for the current tenant.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function assertTenantIsolation(string $modelClass, string $factoryMethod = 'create'): void
    {
        // 1. Setup two distinct tenants
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // 2. Create records in both tenants
        $modelClass::factory()->{$factoryMethod}(['tenant_id' => $tenant1->id]);
        $modelClass::factory()->{$factoryMethod}(['tenant_id' => $tenant1->id]);

        $modelClass::factory()->{$factoryMethod}(['tenant_id' => $tenant2->id]);

        // 3. Act as tenant 1
        TenantContext::set($tenant1);
        $tenant1Results = $modelClass::all();

        // 4. Act as tenant 2
        TenantContext::set($tenant2);
        $tenant2Results = $modelClass::all();

        TenantContext::clear();

        // 5. Assert isolation
        $this->assertCount(2, $tenant1Results, "Tenant 1 should see exactly 2 records of {$modelClass}");
        $this->assertCount(1, $tenant2Results, "Tenant 2 should see exactly 1 record of {$modelClass}");

        foreach ($tenant1Results as $result) {
            $this->assertEquals($tenant1->id, $result->tenant_id, 'Record belongs to wrong tenant');
        }

        foreach ($tenant2Results as $result) {
            $this->assertEquals($tenant2->id, $result->tenant_id, 'Record belongs to wrong tenant');
        }
    }
}
