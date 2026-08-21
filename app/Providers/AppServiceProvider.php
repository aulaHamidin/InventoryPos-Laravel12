<?php

namespace App\Providers;

use App\Actions\Audit\RecordAuditAction;
use App\Auth\TenantUserProvider;
use App\Http\Middleware\EnsureTenantUserActive;
use App\Http\Middleware\SetTenantContext;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Auth::provider('tenant_eloquent', fn ($app, array $config) => new TenantUserProvider($app['hash'], $config['model']));
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Livewire::addPersistentMiddleware([EnsureTenantUserActive::class, SetTenantContext::class]);
        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User || $event->user->tenant === null) {
                return;
            }

            TenantContext::set($event->user->tenant);
            if ($event->guard === 'web' && ! app()->runningInConsole() && request()->hasSession()) {
                request()->session()->put(EnsureTenantUserActive::SESSION_KEY, (int) $event->user->auth_version);
            }
            app(RecordAuditAction::class)->execute(
                'auth.filament_login',
                $event->user,
                $event->user,
                context: app()->runningInConsole() ? null : AuditContext::fromRequest(request()),
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            if (! TenantContext::hasTenant() && $event->user->tenant !== null) {
                TenantContext::set($event->user->tenant);
            }

            app(RecordAuditAction::class)->execute(
                'auth.filament_logout',
                $event->user,
                $event->user,
                context: app()->runningInConsole() ? null : AuditContext::fromRequest(request()),
            );
        });
    }
}
