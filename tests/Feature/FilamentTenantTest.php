<?php

use App\Enums\AdminRole;
use App\Enums\UserRole;
use App\Http\Middleware\EnsureAdminAccess;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Services\TenantContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use PragmaRX\Google2FA\Google2FA;

it('isolates Filament records and gives Staff a read only operational panel', function () {
    [$tenantA, $ownerA] = makeTenantUser();
    $itemA = makeInventoryItem(['nama' => 'ONLY TENANT A']);
    [, $ownerB] = makeTenantUser();
    $itemB = makeInventoryItem(['nama' => 'ONLY TENANT B']);

    TenantContext::set($tenantA);
    $this->actingAs($ownerA)
        ->get('/app/items')
        ->assertOk()
        ->assertSee($itemA->nama)
        ->assertDontSee($itemB->nama);

    TenantContext::run($tenantA, function () use (&$staff): void {
        $staff = makeTenantScopedUser([
            'name' => 'Panel Staff', 'email' => 'panel-staff@example.test',
            'no_hp' => '082222222222', 'password' => 'password',
        ], UserRole::Staff);
    });

    expect($staff->role)->toBe(UserRole::Staff)
        ->and($staff->is_active)->toBeTrue()
        ->and($staff->canAccessPanel(filament()->getPanel('app')))->toBeTrue();
    auth()->forgetGuards();
    $this->flushSession();
    $this->actingAs($staff)->get('/app')->assertOk();
    $this->get('/app/items')->assertOk()->assertSee($itemA->nama)->assertDontSee($itemB->nama);
    $this->get('/app/items/create')->assertForbidden();
    $this->get('/app/shopping-lists')->assertForbidden();
    $this->get('/app/report-exports')->assertForbidden();
});

it('audits Filament login and logout events', function () {
    [, $owner] = makeTenantUser();
    event(new Login('web', $owner, false));
    event(new Logout('web', $owner));

    expect(AuditLog::where('action', 'auth.filament_login')->count())->toBe(1)
        ->and(AuditLog::where('action', 'auth.filament_logout')->count())->toBe(1);
});

it('loads the dedicated Filament theme instead of the Tailwind application entry', function () {
    $this->get('/app/login')
        ->assertOk()
        ->assertSee('/css/filament/admin/theme.css', false)
        ->assertDontSee('/css/filament/filament/app.css', false)
        ->assertDontSee('/resources/css/app.css', false);
});

it('separates platform Admin and tenant User panels', function () {
    [, $owner] = makeTenantUser();
    $admin = new Admin([
        'name' => 'Platform Admin',
        'email' => 'platform-admin@example.test',
        'password' => 'very-secret-password',
    ]);
    $admin->forceFill(['role' => AdminRole::SuperAdmin, 'is_active' => true, 'auth_version' => 1])->save();

    $this->actingAs($owner)->get('/app')->assertOk();
    $this->actingAs($owner)->get('/admin')->assertRedirect('/admin/login');

    auth('web')->logout();
    TenantContext::clear();
    $this->actingAs($admin, 'admin')->withSession([
        EnsureAdminAccess::VERSION_KEY => 1,
    ])->get('/admin')->assertRedirect(route('admin.mfa.setup'));
    $admin->forceFill([
        'two_factor_secret' => app(Google2FA::class)->generateSecretKey(32),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $this->actingAs($admin->fresh(), 'admin')->withSession([
        EnsureAdminAccess::VERSION_KEY => 1,
        EnsureAdminAccess::MFA_KEY => true,
    ])->get('/admin')->assertOk();
    $this->actingAs($admin, 'admin')->get('/app')->assertRedirect('/app/login');
    $this->actingAs($admin, 'admin')->get('/admin/items')->assertNotFound();
});

it('builds the Filament theme from the shared semantic tokens', function () {
    $source = file_get_contents(resource_path('css/filament/admin/theme.css'));
    $compiled = file_get_contents(public_path('css/filament/admin/theme.css'));

    expect($source)
        ->toContain("@import '../../design-tokens.css';")
        ->and($compiled)
        ->toContain('--iq-primary-600:79 70 229')
        ->toContain('scroll-margin-top:8rem')
        ->toContain('@media print');
});
