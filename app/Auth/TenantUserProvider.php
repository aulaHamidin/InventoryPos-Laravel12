<?php

namespace App\Auth;

use App\Models\Scopes\TenantScope;
use Illuminate\Auth\EloquentUserProvider;

class TenantUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        $query = is_null($model)
            ? $this->createModel()->newQueryWithoutScope(TenantScope::class)
            : $model->newQueryWithoutScope(TenantScope::class);

        $query->where('is_active', true);

        with($query, $this->queryCallback);

        return $query;
    }
}
