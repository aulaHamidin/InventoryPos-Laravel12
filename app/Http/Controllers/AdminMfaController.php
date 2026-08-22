<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminAccess;
use App\Models\Admin;
use App\Support\AdminMfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminMfaController extends Controller
{
    public function setup(Request $request, AdminMfaService $mfa): View|RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        if ($admin->two_factor_confirmed_at) {
            return redirect()->route('admin.mfa.challenge');
        }

        return view('admin.mfa-setup', ['qrSvg' => $mfa->qrSvg($admin)]);
    }

    public function confirm(Request $request, AdminMfaService $mfa): View
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $codes = $mfa->confirm($admin, $data['code']);
        $request->session()->put(EnsureAdminAccess::MFA_KEY, true);

        return view('admin.mfa-recovery-codes', ['codes' => $codes]);
    }

    public function challenge(): View
    {
        return view('admin.mfa-challenge');
    }

    public function verify(Request $request, AdminMfaService $mfa): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        /** @var Admin $admin */
        $admin = $request->user('admin');
        if (! $mfa->verify($admin, $data['code'])) {
            return back()->withErrors(['code' => 'Kode autentikasi tidak valid.']);
        }
        $request->session()->put(EnsureAdminAccess::MFA_KEY, true);

        return redirect('/admin');
    }
}
