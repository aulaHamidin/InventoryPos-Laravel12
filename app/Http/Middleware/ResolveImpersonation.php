<?php

namespace App\Http\Middleware;

use App\Actions\Impersonation\EndImpersonationAction;
use App\Actions\Impersonation\StartImpersonationAction;
use App\Enums\ImpersonationEndReason;
use App\Models\Admin;
use App\Models\ImpersonationSession;
use App\Services\ImpersonationContext;
use App\Support\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ResolveImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $id = $request->session()->get(StartImpersonationAction::SESSION_KEY);
        if (! is_numeric($id)) {
            return $next($request);
        }
        $session = ImpersonationSession::query()->with(['admin', 'targetUser', 'tenant'])->find($id);
        $admin = Auth::guard('admin')->user();
        $valid = $session !== null && $admin instanceof Admin && $admin->is_active
            && (int) $session->admin_id === (int) $admin->getKey()
            && hash_equals($session->session_fingerprint_hash, StartImpersonationAction::fingerprint($request->session()->getId(), $admin->getKey()))
            && $session->ended_at === null;
        if (! $valid) {
            abort(403, 'Impersonation session is invalid.');
        }
        if ($session->expires_at->isPast()) {
            app(EndImpersonationAction::class)->execute($session, ImpersonationEndReason::Expired, $admin, context: AuditContext::fromRequest($request));

            return redirect('/admin');
        }
        ImpersonationContext::set($session);
        if (! $request->isMethodSafe() && ! $request->is('livewire/*')) {
            abort(403, 'Impersonation is read-only.');
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        ImpersonationContext::clear();
    }
}
