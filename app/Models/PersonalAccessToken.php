<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public function tokenable()
    {
        return $this->morphTo('tokenable')->withoutGlobalScope(TenantScope::class);
    }
}
