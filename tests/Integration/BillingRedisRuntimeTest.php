<?php

use App\Actions\Admin\CreateSupportAction;
use App\Actions\Admin\ResetSupportAccessAction;
use App\Enums\AdminRole;
use App\Http\Middleware\EnsureAdminAccess;
use App\Models\Admin;
use App\Support\AdminMfaService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

uses(TestCase::class);

function requireBillingRuntime(object $test): void
{
    if (! filter_var(env('BILLING_RUNTIME_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $test->markTestSkipped('Redis Billing runtime harness hanya dijalankan oleh job billing-runtime.');
    }
}

function runtimeAdmin(AdminRole $role, string $email): Admin
{
    $admin = new Admin(['name' => $role->value, 'email' => $email, 'password' => 'runtime-password-123']);
    $admin->forceFill(['role' => $role, 'is_active' => true, 'auth_version' => 1])->save();
    $secret = app(AdminMfaService::class)->ensureSecret($admin);
    app(AdminMfaService::class)->confirm($admin, app(Google2FA::class)->getCurrentOtp($secret));

    return $admin->fresh();
}

it('revokes a Redis-backed Admin session after access reset', function () {
    requireBillingRuntime($this);
    expect(config('session.driver'))->toBe('redis')->and(config('cache.default'))->toBe('redis');

    $superAdmin = runtimeAdmin(AdminRole::SuperAdmin, 'runtime-super@example.test');
    $support = app(CreateSupportAction::class)->execute($superAdmin, 'Runtime Support', 'runtime-support@example.test', 'runtime-password-123');
    $secret = app(AdminMfaService::class)->ensureSecret($support);
    app(AdminMfaService::class)->confirm($support, app(Google2FA::class)->getCurrentOtp($secret));

    $this->actingAs($support->fresh(), 'admin')->withSession([
        EnsureAdminAccess::VERSION_KEY => 1,
        EnsureAdminAccess::MFA_KEY => true,
    ])->get('/admin')->assertOk();
    $oldSessionId = session()->getId();

    app(ResetSupportAccessAction::class)->execute($superAdmin, $support, 'new-runtime-password-123');
    Auth::forgetGuards();
    $this->get('/admin')->assertRedirect('/admin/login');
    expect(session()->getId())->not->toBe($oldSessionId)->and($support->fresh()->auth_version)->toBe(2);
});

it('backs scheduler overlap and single-server locks with Redis', function () {
    requireBillingRuntime($this);
    $events = app(Schedule::class)->events();
    foreach ([
        'billing:sweep-subscriptions' => 180,
        'impersonation:expire' => 30,
        'tenant-deletion:queue-due' => 180,
        'tenant-deletion:purge' => 180,
    ] as $command => $expiry) {
        $event = collect($events)->first(fn ($candidate): bool => str_contains((string) $candidate->command, $command));
        expect($event)->not->toBeNull()
            ->and($event->timezone)->toBe('Asia/Jakarta')
            ->and($event->withoutOverlapping)->toBeTrue()
            ->and($event->expiresAt)->toBe($expiry)
            ->and($event->onOneServer)->toBeTrue();

        $event->mutex->forget($event);
        expect($event->shouldSkipDueToOverlapping())->toBeFalse()
            ->and($event->shouldSkipDueToOverlapping())->toBeTrue();
        $event->mutex->forget($event);
    }
});
