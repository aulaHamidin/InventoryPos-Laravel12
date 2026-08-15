<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\TenantContext;
use Laravel\Sanctum\Sanctum;

it('returns the standard 401 and validation error envelopes', function () {
    TenantContext::clear();

    $this->getJson('/api/v1/items')
        ->assertUnauthorized()
        ->assertExactJson([
            'status' => 'error', 'message' => 'Unauthenticated.',
            'error_code' => 'UNAUTHENTICATED', 'errors' => [],
        ]);

    [, $owner] = makeTenantUser();
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/stock/movements/in', [])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['status', 'message', 'error_code', 'errors']);
});

it('allows Owner, denies Staff, and hides cross tenant IDs as 404', function () {
    [$tenantA, $ownerA] = makeTenantUser();
    $itemA = makeInventoryItem();
    TenantContext::run($tenantA, function () use (&$staff): void {
        $staff = User::create([
            'name' => 'Staff', 'email' => 'staff@example.test', 'no_hp' => '081111111111',
            'password' => 'password', 'role' => UserRole::Staff,
        ]);
    });

    Sanctum::actingAs($ownerA);
    $this->getJson('/api/v1/items')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['status', 'message', 'data']);

    Sanctum::actingAs($staff);
    $this->getJson('/api/v1/items')
        ->assertForbidden()
        ->assertJsonPath('error_code', 'FORBIDDEN');

    [, $ownerB] = makeTenantUser();
    $itemB = makeInventoryItem();
    TenantContext::set($tenantA);
    Sanctum::actingAs($ownerA);

    $this->getJson("/api/v1/items/{$itemB->id}/suppliers")
        ->assertNotFound()
        ->assertJsonPath('error_code', 'NOT_FOUND');

    expect($ownerB->canAccessPanel(filament()->getPanel('app')))->toBeTrue()
        ->and($staff->canAccessPanel(filament()->getPanel('app')))->toBeFalse();
});

it('uses canonical stock and item supplier API contracts', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 0]);
    $supplier = Supplier::create(['nama' => 'API Supplier']);
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/stock/movements/in', [
        'item_id' => $item->id, 'qty' => 2, 'harga_satuan' => '75.00',
        'supplier_id' => $supplier->id,
    ])->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.movement_type', 'stock_in')
        ->assertJsonPath('data.harga_satuan', '75.00');

    $response = $this->postJson("/api/v1/items/{$item->id}/suppliers", [
        'supplier_id' => $supplier->id, 'harga_beli_terakhir' => '72.50', 'is_preferred' => true,
    ])->assertCreated()->assertJsonPath('data.is_preferred', true);

    expect(ItemSupplier::withoutGlobalScopes()->where('item_id', $item->id)->where('supplier_id', $supplier->id)->firstOrFail()->is_preferred)->toBeTrue();
});

it('audits API login and logout using boundary metadata', function () {
    [, $owner] = makeTenantUser();
    TenantContext::clear();

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $owner->email, 'password' => 'password', 'device_name' => 'contract-test',
    ])->assertOk()->assertJsonPath('status', 'success');

    $token = $login->json('data.token');
    $this->withToken($token)->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('status', 'success');

    TenantContext::set($owner->tenant);
    expect(AuditLog::where('action', 'auth.login')->count())->toBe(1)
        ->and(AuditLog::where('action', 'auth.logout')->count())->toBe(1)
        ->and(AuditLog::whereNotNull('ip_address')->count())->toBeGreaterThanOrEqual(2);
});

it('exposes the canonical Shopping List lifecycle through API v1', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 1, 'stok_minimal' => 4]);
    $supplier = Supplier::create(['nama' => 'Lifecycle Supplier']);
    Sanctum::actingAs($owner);

    $generated = $this->postJson('/api/v1/shopping-lists/generate')
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');
    $listId = $generated->json('data.id');
    $lineId = $generated->json('data.items.0.id');

    $this->postJson("/api/v1/shopping-lists/{$listId}/submit", ['items' => [[
        'shopping_list_item_id' => $lineId,
        'is_checked' => true,
        'supplier_id' => $supplier->id,
        'qty_dibeli' => 3,
    ]]])->assertOk()->assertJsonPath('data.status', 'purchased');

    $this->postJson("/api/v1/shopping-lists/{$listId}/receive", ['items' => [[
        'shopping_list_item_id' => $lineId,
        'qty_received' => 2,
        'harga_satuan' => '65.00',
    ]]])->assertOk()->assertJsonPath('data.status', 'completed');

    expect(Item::withoutGlobalScopes()->findOrFail($item->id)->stok_saat_ini)->toBe(3);
});

it('denies report files and financial export creation to Staff', function () {
    [$tenant] = makeTenantUser();
    TenantContext::run($tenant, function () use (&$staff): void {
        $staff = User::create([
            'name' => 'Report Staff', 'email' => 'report-staff@example.test',
            'no_hp' => '083333333333', 'password' => 'password', 'role' => UserRole::Staff,
        ]);
    });
    Sanctum::actingAs($staff);

    $this->postJson('/api/v1/reports/exports', [
        'report_type' => 'pos', 'format' => 'xlsx', 'filters' => [],
    ])->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN');
});

it('serializes the canonical POS cash contract through API v1', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 5, 'harga_jual' => '100.00']);
    Sanctum::actingAs($owner);

    $checkout = $this->withHeader('Idempotency-Key', 'api-pos-contract-key')
        ->postJson('/api/v1/pos/checkout', ['items' => [[
            'item_id' => $item->id, 'qty' => 2, 'discount_amount' => '10.00',
        ]]])->assertCreated()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.subtotal_amount', '200.00')
        ->assertJsonPath('data.discount_amount', '10.00')
        ->assertJsonPath('data.total_amount', '190.00');

    $transactionId = $checkout->json('data.id');

    $this->postJson("/api/v1/pos/transactions/{$transactionId}/pay-cash", [
        'cash_received' => '250.00',
    ])->assertOk()
        ->assertJsonPath('data.transaction_id', $transactionId)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.change_amount', '60.00');

    $this->getJson("/api/v1/pos/transactions/{$transactionId}/status")
        ->assertOk()
        ->assertJsonPath('data.transaction_status', 'completed')
        ->assertJsonPath('data.payment.method', 'cash')
        ->assertJsonPath('data.payment.status', 'paid');
});

it('supports PUT and DELETE Item Supplier through audited Actions', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem();
    $supplier = Supplier::create(['nama' => 'Mutable Link Supplier']);
    Sanctum::actingAs($owner);

    $created = $this->postJson("/api/v1/items/{$item->id}/suppliers", [
        'supplier_id' => $supplier->id, 'is_preferred' => true,
    ])->assertCreated();
    $linkId = $created->json('data.id');

    $this->putJson("/api/v1/item-suppliers/{$linkId}", [
        'supplier_sku' => 'SKU-UPDATED', 'is_preferred' => false,
    ])->assertOk()
        ->assertJsonPath('data.supplier_sku', 'SKU-UPDATED')
        ->assertJsonPath('data.is_preferred', false);

    $this->deleteJson("/api/v1/item-suppliers/{$linkId}")
        ->assertOk()
        ->assertJsonPath('data', null);

    expect(ItemSupplier::withoutGlobalScopes()->find($linkId))->toBeNull();
});
