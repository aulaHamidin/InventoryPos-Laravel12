<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

final class AdminUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        $query = is_null($model) ? $this->createModel()->newQuery() : $model->newQuery();
        $query->where('is_active', true);
        with($query, $this->queryCallback);

        return $query;
    }
}
