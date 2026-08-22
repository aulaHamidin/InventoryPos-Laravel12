<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminAccess
{
    public const VERSION_KEY = 'admin_auth_version';

    public const MFA_KEY = 'admin_mfa_verified';

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin instanceof Admin || ! $admin->is_active || (int) $request->session()->get(self::VERSION_KEY, 0) !== (int) $admin->auth_version) {
            return $this->reject($request);
        }
        if (($request->routeIs('admin.mfa.setup') || $request->routeIs('admin.mfa.confirm')) && ! $admin->two_factor_confirmed_at) {
            return $next($request);
        }
        if (($request->routeIs('admin.mfa.challenge') || $request->routeIs('admin.mfa.verify')) && $admin->two_factor_confirmed_at) {
            return $next($request);
        }
        if (! $admin->two_factor_confirmed_at) {
            return redirect()->route('admin.mfa.setup');
        }
        if ($request->session()->get(self::MFA_KEY) !== true) {
            return redirect()->route('admin.mfa.challenge');
        }

        return $next($request);
    }

    private function reject(Request $request): Response
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->guest('/admin/login');
    }
}
