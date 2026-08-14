<?php

namespace App\Support;

use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use LogicException;

class OwnershipGuard
{
    public static function forTenant(Tenant $tenant, string $modelClass, int|string $id): Model
    {
        $model = $modelClass::withoutGlobalScopes()->findOrFail($id);

        if (! array_key_exists('tenant_id', $model->getAttributes())) {
            throw new LogicException("Model [{$modelClass}] is not tenant-scoped.");
        }

        if ((int) $model->getAttribute('tenant_id') !== (int) $tenant->getKey()) {
            throw (new ModelNotFoundException)->setModel($modelClass, [$id]);
        }

        return $model;
    }

    public static function validate(string $modelClass, int|string $id): Model
    {
        return static::forTenant(TenantContext::get(), $modelClass, $id);
    }

    public static function forTenantMany(Tenant $tenant, string $modelClass, array $ids): Collection
    {
        return collect(array_values(array_unique($ids)))
            ->map(fn (int|string $id): Model => static::forTenant($tenant, $modelClass, $id));
    }
}
