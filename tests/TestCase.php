<?php

namespace Tests;

use App\Http\Middleware\EnsureTenantUserActive;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($user instanceof User && ($guard === null || $guard === 'web')) {
            $this->withSession([
                EnsureTenantUserActive::SESSION_KEY => (int) $user->auth_version,
            ]);
        }

        return $this;
    }
}
