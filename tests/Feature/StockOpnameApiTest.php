<?php

use App\Enums\UserRole;
use App\Models\Rack;
use App\Models\User;
use App\Services\TenantContext;
use Laravel\Sanctum\Sanctum;

it('exposes the tenant scoped cycle counting API contract', function () {
    [, $owner] = makeTenantUser();
    $rack = Rack::create(['kode' => 'API', 'nama' => 'Rak API']);
    $item = makeInventoryItem(['rack_id' => $rack->id, 'stok_saat_ini' => 6]);
    Sanctum::actingAs($owner);

    $created = $this->postJson('/api/v1/opname', ['scope_type' => 'partial', 'rack_id' => $rack->id])
        ->assertCreated()
        ->assertJsonPath('data.scope_type', 'partial')
        ->assertJsonPath('data.progress.total', 1);
    $id = $created->json('data.id');

    $this->getJson('/api/v1/opname')->assertOk()
        ->assertJsonPath('data.data.0.id', $id)
        ->assertJsonPath('data.data.0.progress.counted', 0);

    $this->putJson("/api/v1/opname/{$id}/details", ['items' => [[
        'item_id' => $item->id, 'qty_fisik' => 4, 'note' => 'API count',
    ]]])->assertOk()
        ->assertJsonPath('data.0.qty_sistem_at_count', 6)
        ->assertJsonPath('data.0.qty_fisik', 4);

    $this->postJson("/api/v1/opname/{$id}/finalize")
        ->assertOk()
        ->assertJsonPath('data.opname.status', 'completed')
        ->assertJsonPath('data.summary.total_units_out', 2);

    $this->postJson("/api/v1/opname/{$id}/finalize")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INVALID_STATE_TRANSITION');
});

it('returns canonical validation conflict and incomplete errors', function () {
    [, $owner] = makeTenantUser();
    $rack = Rack::create(['kode' => 'ERR', 'nama' => 'Rak Error']);
    makeInventoryItem(['rack_id' => $rack->id]);
    Sanctum::actingAs($owner);

    $id = $this->postJson('/api/v1/opname', ['scope_type' => 'partial', 'rack_id' => $rack->id])
        ->assertCreated()->json('data.id');
    $this->postJson('/api/v1/opname', ['scope_type' => 'partial', 'rack_id' => $rack->id])
        ->assertStatus(409)->assertJsonPath('error_code', 'OPNAME_SCOPE_CONFLICT');
    $this->postJson("/api/v1/opname/{$id}/finalize")
        ->assertUnprocessable()->assertJsonPath('error_code', 'OPNAME_INCOMPLETE');
    $this->putJson("/api/v1/opname/{$id}/details", ['items' => [[
        'item_id' => 1, 'qty_fisik' => -1,
    ]]])->assertUnprocessable()->assertJsonPath('error_code', 'VALIDATION_ERROR');
});

it('hides foreign opname ids and denies Staff', function () {
    [$tenantA, $ownerA] = makeTenantUser();
    $rack = Rack::create(['kode' => 'A', 'nama' => 'Rak A']);
    makeInventoryItem(['rack_id' => $rack->id]);
    Sanctum::actingAs($ownerA);
    $id = $this->postJson('/api/v1/opname', ['scope_type' => 'partial', 'rack_id' => $rack->id])->json('data.id');

    [, $ownerB] = makeTenantUser();
    Sanctum::actingAs($ownerB);
    $this->postJson("/api/v1/opname/{$id}/finalize")->assertNotFound();

    TenantContext::set($tenantA);
    $staff = User::create([
        'name' => 'Opname Staff', 'email' => 'opname-staff@example.test', 'no_hp' => '081234567890',
        'password' => 'password', 'role' => UserRole::Staff,
    ]);
    Sanctum::actingAs($staff);
    $this->getJson('/api/v1/opname')->assertForbidden();
});
