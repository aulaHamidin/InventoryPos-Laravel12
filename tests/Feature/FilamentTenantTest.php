<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

it('isolates Filament records and rejects Staff panel access', function () {
    [$tenantA, $ownerA] = makeTenantUser();
    $itemA = makeInventoryItem(['nama' => 'ONLY TENANT A']);
    [, $ownerB] = makeTenantUser();
    $itemB = makeInventoryItem(['nama' => 'ONLY TENANT B']);

    TenantContext::set($tenantA);
    $this->actingAs($ownerA)
        ->get('/admin/items')
        ->assertOk()
        ->assertSee($itemA->nama)
        ->assertDontSee($itemB->nama);

    TenantContext::run($tenantA, function () use (&$staff): void {
        $staff = User::create([
            'name' => 'Panel Staff', 'email' => 'panel-staff@example.test',
            'no_hp' => '082222222222', 'password' => 'password', 'role' => UserRole::Staff,
        ]);
    });

    $this->actingAs($staff)->get('/admin')->assertForbidden();
});

it('audits Filament login and logout events', function () {
    [, $owner] = makeTenantUser();
    event(new Login('web', $owner, false));
    event(new Logout('web', $owner));

    expect(AuditLog::where('action', 'auth.filament_login')->count())->toBe(1)
        ->and(AuditLog::where('action', 'auth.filament_logout')->count())->toBe(1);
});

it('loads the dedicated Filament theme instead of the Tailwind application entry', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('/css/filament/admin/theme.css', false)
        ->assertDontSee('/css/filament/filament/app.css', false)
        ->assertDontSee('/resources/css/app.css', false);
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
