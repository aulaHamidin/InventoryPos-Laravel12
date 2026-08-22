<?php

namespace App\Http\Controllers;

use App\Actions\Impersonation\EndImpersonationAction;
use App\Actions\Impersonation\StartImpersonationAction;
use App\Enums\ImpersonationEndReason;
use App\Models\ImpersonationSession;
use App\Support\AuditContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdminImpersonationController extends Controller
{
    public function end(Request $request, EndImpersonationAction $action): RedirectResponse
    {
        $id = $request->session()->get(StartImpersonationAction::SESSION_KEY);
        abort_unless(is_numeric($id), 404);
        $impersonation = ImpersonationSession::query()->findOrFail($id);
        $action->execute($impersonation, ImpersonationEndReason::Explicit, $request->user('admin'), context: AuditContext::fromRequest($request));

        return redirect('/admin');
    }
}
