<?php

use App\Http\Controllers\ReportController;
use App\Http\Middleware\EnsureTenantUserActive;
use App\Http\Middleware\SetTenantContext;
use App\Livewire\PosScreen;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app/login');

Route::get('/login', fn () => redirect('/app/login'))->name('login');

Route::prefix('app')->middleware(['auth', EnsureTenantUserActive::class, SetTenantContext::class])->group(function (): void {
    Route::get('/pos', PosScreen::class)->name('pos');

    Route::post('/reports/exports', [ReportController::class, 'queue'])->name('reports.exports.queue');
    Route::get('/reports/exports/{export}', [ReportController::class, 'status'])->name('reports.exports.status');
    Route::get('/reports/exports/{export}/download', [ReportController::class, 'download'])->name('reports.exports.download');
});
