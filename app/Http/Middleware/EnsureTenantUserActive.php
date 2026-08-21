<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUserActive
{
    public const SESSION_KEY = 'tenant_auth_version';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->is_active || $user->tenant === null || ! $user->tenant->canOperate()) {
            return $this->reject($request);
        }

        $usesAccessToken = $user->currentAccessToken() !== null;
        if ($request->hasSession() && ! $usesAccessToken) {
            $sessionVersion = $request->session()->get(self::SESSION_KEY);
            if (! is_numeric($sessionVersion) || (int) $sessionVersion !== (int) $user->auth_version) {
                return $this->reject($request);
            }
        }

        return $next($request);
    }

    private function reject(Request $request): Response
    {
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        TenantContext::clear();

        if ($request->is('api/*') || $request->expectsJson()) {
            abort(401, 'Unauthenticated.');
        }

        return redirect()->guest('/app/login');
    }
}
