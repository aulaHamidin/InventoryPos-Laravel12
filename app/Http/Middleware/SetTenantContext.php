<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            if ($user->tenant_id === null || $user->tenant === null || ! $user->tenant->canOperate()) {
                abort(403, 'Tenant context is unavailable.');
            }

            TenantContext::set($user->tenant);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        TenantContext::clear();
    }
}
