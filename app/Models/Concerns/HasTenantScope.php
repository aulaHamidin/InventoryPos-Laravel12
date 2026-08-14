<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait HasTenantScope
{
    protected static function bootHasTenantScope(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            $tenantId = TenantContext::hasTenant() ? TenantContext::id() : null;

            if ($tenantId === null) {
                throw new LogicException('Tenant context is required for tenant-scoped writes.');
            }

            $model->tenant_id = $tenantId;
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
