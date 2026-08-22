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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (config('cache.default') === 'redis') {
            app('router')->aliasMiddleware('throttle', ThrottleRequestsWithRedis::class);
        }

        RateLimiter::for('api-login', function (Request $request): Limit {
            $email = Str::lower(trim((string) $request->input('email')));

            return $this->apiLimit(5, hash('sha256', $email.'|'.($request->ip() ?? 'unknown')));
        });
        RateLimiter::for('api-read', fn (Request $request): Limit => $this->apiLimit(300, $this->tenantUserKey($request)));
        RateLimiter::for('api-write', fn (Request $request): Limit => $this->apiLimit(120, $this->tenantUserKey($request)));
        RateLimiter::for('api-export', fn (Request $request): Limit => $this->apiLimit(10, $this->tenantUserKey($request)));

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

    private function apiLimit(int $attempts, string $key): Limit
    {
        return Limit::perMinute($attempts)
            ->by($key)
            ->response(fn (Request $request, array $headers) => response()->json([
                'status' => 'error',
                'message' => 'Terlalu banyak permintaan. Coba lagi nanti.',
                'error_code' => 'RATE_LIMITED',
                'errors' => [],
            ], 429, $headers));
    }

    private function tenantUserKey(Request $request): string
    {
        $user = $request->user();

        return hash('sha256', sprintf('%s:%s', $user?->tenant_id ?? 'missing-tenant', $user?->getAuthIdentifier() ?? 'missing-user'));
    }
}
