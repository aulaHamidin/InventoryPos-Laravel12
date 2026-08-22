<?php

use App\Http\Controllers\AdminImpersonationController;
use App\Http\Controllers\AdminMfaController;
use App\Http\Controllers\ReportController;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureTenantUserActive;
use App\Http\Middleware\ResolveImpersonation;
use App\Http\Middleware\SetTenantContext;
use App\Livewire\PosScreen;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app/login');

Route::get('/login', fn () => redirect('/app/login'))->name('login');

Route::prefix('admin/mfa')->middleware(['auth:admin', EnsureAdminAccess::class])->group(function (): void {
    Route::get('/setup', [AdminMfaController::class, 'setup'])->name('admin.mfa.setup');
    Route::post('/setup', [AdminMfaController::class, 'confirm'])->middleware('throttle:admin-mfa')->name('admin.mfa.confirm');
    Route::get('/challenge', [AdminMfaController::class, 'challenge'])->name('admin.mfa.challenge');
    Route::post('/challenge', [AdminMfaController::class, 'verify'])->middleware('throttle:admin-mfa')->name('admin.mfa.verify');
});

Route::prefix('app')->middleware(['auth', EnsureTenantUserActive::class, SetTenantContext::class])->group(function (): void {
    Route::post('/impersonation/end', [AdminImpersonationController::class, 'end'])->middleware('auth:admin')->name('impersonation.end');

    Route::middleware(ResolveImpersonation::class)->group(function (): void {
        Route::get('/pos', PosScreen::class)->middleware('subscription:operate')->name('pos');

        Route::post('/reports/exports', [ReportController::class, 'queue'])->middleware('subscription:configure')->name('reports.exports.queue');
        Route::get('/reports/exports/{export}', [ReportController::class, 'status'])->middleware('subscription:read')->name('reports.exports.status');
        Route::get('/reports/exports/{export}/download', [ReportController::class, 'download'])->middleware('subscription:read')->name('reports.exports.download');
    });
});
