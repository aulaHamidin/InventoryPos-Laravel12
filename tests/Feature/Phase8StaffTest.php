<?php

use App\Actions\Inventory\StockInAction;
use App\Actions\Inventory\UpdateItemAction;
use App\Actions\Inventory\UpsertItemSupplierAction;
use App\Actions\MasterData\CreateMasterDataAction;
use App\Actions\Opname\CreateOpnameAction;
use App\Actions\Pos\PayCashAction;
use App\Actions\Reports\QueueReportExportAction;
use App\Actions\Shopping\GenerateShoppingListAction;
use App\Actions\Staff\ActivateStaffAction;
use App\Actions\Staff\CreateStaffAction;
use App\Actions\Staff\DeactivateStaffAction;
use App\Actions\Staff\ResetStaffAccessAction;
use App\Actions\Staff\UpdateStaffProfileAction;
use App\Enums\UserRole;
use App\Http\Middleware\EnsureTenantUserActive;
use App\Livewire\PosScreen;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\PosPayment;
use App\Models\PosTransaction;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\TenantContext;
use App\Support\AuditContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

it('installs the F8 access columns and keeps protected user attributes out of generic mass assignment', function () {
    expect(Schema::hasColumns('users', ['is_active', 'auth_version']))->toBeTrue();

    [, $owner] = makeTenantUser();
    $staff = app(CreateStaffAction::class)->execute([
        'name' => 'Kasir Aman',
        'email' => 'kasir-aman@example.test',
        'no_hp' => '081200000001',
        'password' => 'PasswordAwal12',
        'password_confirmation' => 'PasswordAwal12',
        'role' => UserRole::Owner->value,
        'is_active' => false,
        'auth_version' => 99,
        'tenant_id' => 999,
    ], $owner);

    expect($staff->role)->toBe(UserRole::Staff)
        ->and($staff->tenant_id)->toBe($owner->tenant_id)
        ->and($staff->is_active)->toBeTrue()
        ->and($staff->auth_version)->toBe(1);

    $staff->fill([
        'role' => UserRole::Owner->value,
        'tenant_id' => 999,
        'is_active' => false,
        'auth_version' => 77,
    ])->save();

    $staff->refresh();
    expect($staff->role)->toBe(UserRole::Staff)
        ->and($staff->tenant_id)->toBe($owner->tenant_id)
        ->and($staff->is_active)->toBeTrue()
        ->and($staff->auth_version)->toBe(1);
});

it('manages the complete Staff lifecycle with canonical audits and irreversible old access', function () {
    [$tenant, $owner] = makeTenantUser();
    $context = new AuditContext('127.0.0.1', 'phase8-test');
    $staff = app(CreateStaffAction::class)->execute([
        'name' => 'Kasir Satu',
        'email' => 'kasir-satu@example.test',
        'no_hp' => '081200000002',
        'password' => 'PasswordAwal12',
        'password_confirmation' => 'PasswordAwal12',
    ], $owner, $context);

    expect($staff->role)->toBe(UserRole::Staff)
        ->and($staff->is_active)->toBeTrue()
        ->and(Hash::check('PasswordAwal12', $staff->password))->toBeTrue();

    $updated = app(UpdateStaffProfileAction::class)->execute($staff->id, [
        'name' => 'Kasir Utama',
        'email' => 'kasir-utama@example.test',
        'no_hp' => '081200000003',
        'role' => UserRole::Owner->value,
    ], $owner, $context);
    expect($updated->name)->toBe('Kasir Utama')->and($updated->role)->toBe(UserRole::Staff);

    $oldToken = $staff->createToken('old-device')->plainTextToken;
    app(DeactivateStaffAction::class)->execute($staff->id, $owner, $context);
    app(DeactivateStaffAction::class)->execute($staff->id, $owner, $context);
    expect($staff->fresh()->is_active)->toBeFalse()
        ->and($staff->fresh()->auth_version)->toBe(2)
        ->and($staff->tokens()->count())->toBe(0)
        ->and(AuditLog::where('action', 'staff.deactivated')->count())->toBe(1);

    $this->withToken($oldToken)->getJson('/api/v1/items')->assertUnauthorized();

    TenantContext::set($tenant);
    app(ActivateStaffAction::class)->execute($staff->id, $owner, $context);
    app(ActivateStaffAction::class)->execute($staff->id, $owner, $context);
    expect($staff->fresh()->is_active)->toBeTrue()
        ->and($staff->fresh()->auth_version)->toBe(2)
        ->and(AuditLog::where('action', 'staff.activated')->count())->toBe(1);
    $this->withToken($oldToken)->getJson('/api/v1/items')->assertUnauthorized();

    TenantContext::set($tenant);
    $resetToken = $staff->createToken('reset-device')->plainTextToken;
    app(ResetStaffAccessAction::class)->execute($staff->id, [
        'password' => 'PasswordBaru12',
        'password_confirmation' => 'PasswordBaru12',
    ], $owner, $context);
    expect($staff->fresh()->auth_version)->toBe(3)
        ->and(Hash::check('PasswordBaru12', $staff->fresh()->password))->toBeTrue()
        ->and($staff->tokens()->count())->toBe(0);
    $this->withToken($resetToken)->getJson('/api/v1/items')->assertUnauthorized();

    TenantContext::set($tenant);
    $auditPayload = AuditLog::query()->whereIn('action', [
        'staff.created', 'staff.profile_updated', 'staff.access_reset', 'staff.activated', 'staff.deactivated',
    ])->get()->map->only(['action', 'old_values', 'new_values', 'metadata'])->toJson();
    expect($auditPayload)->not->toContain('PasswordAwal12')
        ->not->toContain('PasswordBaru12')
        ->not->toContain($oldToken)
        ->not->toContain($resetToken)
        ->and(AuditLog::where('action', 'staff.created')->count())->toBe(1)
        ->and(AuditLog::where('action', 'staff.profile_updated')->count())->toBe(1)
        ->and(AuditLog::where('action', 'staff.access_reset')->count())->toBe(1);

    expect(fn () => app(DeactivateStaffAction::class)->execute($owner->id, $owner))
        ->toThrow(AuthorizationException::class);

    [, $foreignOwner] = makeTenantUser();
    $foreignStaff = makeTenantScopedUser([
        'name' => 'Kasir Asing',
        'email' => 'kasir-asing@example.test',
        'no_hp' => '081200000004',
        'password' => 'PasswordAsing12',
    ], UserRole::Staff);
    TenantContext::set($tenant);
    expect(fn () => app(DeactivateStaffAction::class)->execute($foreignStaff->id, $owner))
        ->toThrow(ModelNotFoundException::class);

    expect(fn () => app(CreateStaffAction::class)->execute([
        'name' => 'Duplicate',
        'email' => $foreignOwner->email,
        'no_hp' => '081200000005',
        'password' => 'PasswordDupe12',
        'password_confirmation' => 'PasswordDupe12',
    ], $owner))->toThrow(ValidationException::class);
});

it('rejects inactive login generically and invalidates missing or stale web auth versions', function () {
    [$tenant, $owner] = makeTenantUser();
    $staff = app(CreateStaffAction::class)->execute([
        'name' => 'Kasir Session',
        'email' => 'kasir-session@example.test',
        'no_hp' => '081200000006',
        'password' => 'PasswordLogin12',
        'password_confirmation' => 'PasswordLogin12',
    ], $owner);

    $this->actingAs($staff)->get('/app/items')->assertOk();
    TenantContext::set($tenant);
    app(ResetStaffAccessAction::class)->execute($staff->id, [
        'password' => 'PasswordReset12',
        'password_confirmation' => 'PasswordReset12',
    ], $owner);
    auth()->forgetGuards();
    $this->get('/app/items')->assertRedirect('/app/login');

    TenantContext::set($tenant);
    app(DeactivateStaffAction::class)->execute($staff->id, $owner);
    TenantContext::clear();
    expect(auth('web')->attempt([
        'email' => $staff->email,
        'password' => 'PasswordReset12',
    ]))->toBeFalse();
    $this->postJson('/api/v1/auth/login', [
        'email' => $staff->email,
        'password' => 'PasswordReset12',
        'device_name' => 'inactive-test',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Kredensial tidak valid.');

    TenantContext::set($tenant);
    app(ActivateStaffAction::class)->execute($staff->id, $owner);
    $this->flushSession();
    $this->actingAs($staff->fresh(), 'web')->withSession([
        EnsureTenantUserActive::SESSION_KEY => null,
    ]);
    $this->get('/app/items')->assertRedirect('/app/login');
});

it('exposes only Staff-safe inventory and Filament surfaces', function () {
    [, $owner] = makeTenantUser();
    $staff = app(CreateStaffAction::class)->execute([
        'name' => 'Kasir Projection',
        'email' => 'kasir-projection@example.test',
        'no_hp' => '081200000007',
        'password' => 'PasswordStaff12',
        'password_confirmation' => 'PasswordStaff12',
    ], $owner);
    $item = makeInventoryItem([
        'nama' => 'Barang Aman Staff',
        'harga_beli' => '777.00',
        'average_cost' => '666.00',
        'harga_jual' => '999.00',
    ]);
    $supplier = Supplier::create([
        'nama' => 'Supplier Aman',
        'kontak' => '081200000099',
        'alamat' => 'Alamat Uji',
    ]);
    app(UpsertItemSupplierAction::class)->execute($item->id, $supplier->id, [
        'supplier_sku' => 'SUP-SAFE',
        'harga_beli_terakhir' => '888.00',
        'lead_time_days' => 0,
        'is_preferred' => true,
    ], $owner);

    Sanctum::actingAs($staff);
    $items = $this->getJson('/api/v1/items')->assertOk();
    $links = $this->getJson("/api/v1/items/{$item->id}/suppliers")->assertOk();
    foreach ([$items->json(), $links->json()] as $projection) {
        $encoded = mb_strtolower(json_encode($projection, JSON_THROW_ON_ERROR));
        expect($encoded)->not->toContain('harga_beli')
            ->not->toContain('average_cost')
            ->not->toContain('tenant_id')
            ->not->toContain('margin')
            ->not->toContain('profit')
            ->not->toContain('valuation');
    }

    $this->postJson('/api/v1/stock/movements/in', [
        'item_id' => $item->id,
        'qty' => 1,
        'harga_satuan' => '1.00',
    ])->assertForbidden();
    $this->postJson("/api/v1/items/{$item->id}/suppliers", [
        'supplier_id' => $supplier->id,
    ])->assertForbidden();

    TenantContext::set($owner->tenant);
    $forgedMutations = [
        fn () => app(StockInAction::class)->execute($item->id, 1, '1.00', $staff),
        fn () => app(UpdateItemAction::class)->execute($item->id, ['nama' => 'Forged'], $staff),
        fn () => app(UpsertItemSupplierAction::class)->execute($item->id, $supplier->id, [], $staff),
        fn () => app(CreateMasterDataAction::class)->execute(Category::class, ['kode' => 'FORGED', 'nama' => 'Forged'], $staff),
        fn () => app(CreateOpnameAction::class)->execute('full', $staff),
        fn () => app(GenerateShoppingListAction::class)->execute($staff),
        fn () => app(QueueReportExportAction::class)->execute('stock', 'xlsx', [], $staff),
    ];
    foreach ($forgedMutations as $mutation) {
        expect($mutation)->toThrow(AuthorizationException::class);
    }

    $forgedOwnerActor = clone $staff;
    $forgedOwnerActor->forceFill(['role' => UserRole::Owner]);
    expect(fn () => app(StockInAction::class)->execute($item->id, 1, '1.00', $forgedOwnerActor))
        ->toThrow(AuthorizationException::class);

    auth()->forgetGuards();
    $this->flushSession();
    $this->actingAs($staff, 'web');
    $this->get('/app/items')
        ->assertOk()
        ->assertSee('Barang Aman Staff')
        ->assertDontSee('harga_beli', false)
        ->assertDontSee('777.00', false)
        ->assertDontSee('666.00', false)
        ->assertDontSee('window.print()', false);
    $this->get('/app/suppliers')
        ->assertOk()
        ->assertSee('Supplier Aman')
        ->assertDontSee('888.00', false);
    $this->get('/app/items/create')->assertForbidden();
    $this->get("/app/items/{$item->id}/edit")->assertForbidden();
    $this->get('/app/suppliers/create')->assertForbidden();
    $this->get('/app/staff')->assertForbidden();
    $this->get('/app/analytics-settings')->assertForbidden();
    $this->get('/app/stock-movements')->assertForbidden();
    $this->get('/app/stock-opnames')->assertForbidden();
    $this->get('/app/shopping-lists')->assertForbidden();
    $this->get('/app/report-exports')->assertForbidden();
    expect($this->get('/app/receive-shopping-list?list=1')->status())->toBeIn([403, 404]);

    TenantContext::set($owner->tenant);
    $component = Livewire::actingAs($staff, 'web')
        ->test(PosScreen::class)
        ->set('searchQuery', 'Barang Aman');
    $state = mb_strtolower(json_encode($component->get('searchResults'), JSON_THROW_ON_ERROR));
    expect($state)->toContain('barang aman staff')
        ->not->toContain('harga_beli')
        ->not->toContain('average_cost')
        ->not->toContain('tenant_id');
});

it('allows three Staff payment methods with discounts while enforcing cashier scope and tenant idempotency', function () {
    [, $owner] = makeTenantUser();
    $staffOne = app(CreateStaffAction::class)->execute([
        'name' => 'Kasir POS Satu',
        'email' => 'kasir-pos-satu@example.test',
        'no_hp' => '081200000008',
        'password' => 'PasswordKasir12',
        'password_confirmation' => 'PasswordKasir12',
    ], $owner);
    $staffTwo = app(CreateStaffAction::class)->execute([
        'name' => 'Kasir POS Dua',
        'email' => 'kasir-pos-dua@example.test',
        'no_hp' => '081200000009',
        'password' => 'PasswordKasir12',
        'password_confirmation' => 'PasswordKasir12',
    ], $owner);
    $item = makeInventoryItem(['stok_saat_ini' => 20, 'harga_jual' => '100.00']);
    $payload = ['items' => [[
        'item_id' => $item->id,
        'qty' => 1,
        'discount_amount' => '10.00',
    ]]];

    Sanctum::actingAs($staffOne);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/pos/checkout', array_merge($payload, ['cashier_id' => $staffTwo->id]))
        ->assertUnprocessable();

    $cashKey = (string) Str::uuid();
    $cashCheckout = $this->withHeader('Idempotency-Key', $cashKey)
        ->postJson('/api/v1/pos/checkout', $payload)
        ->assertCreated()
        ->assertJsonPath('data.total_amount', '90.00');
    $cashId = $cashCheckout->json('data.id');
    $this->withHeader('Idempotency-Key', $cashKey)
        ->postJson('/api/v1/pos/checkout', $payload)
        ->assertCreated()
        ->assertJsonPath('data.id', $cashId);
    $this->withHeader('Idempotency-Key', $cashKey)
        ->postJson('/api/v1/pos/checkout', ['items' => [[
            'item_id' => $item->id, 'qty' => 2, 'discount_amount' => '10.00',
        ]]])->assertConflict()->assertJsonPath('error_code', 'IDEMPOTENCY_CONFLICT');
    $this->postJson("/api/v1/pos/transactions/{$cashId}/pay-cash", [
        'cash_received' => '100.00',
    ])->assertOk()->assertJsonPath('data.change_amount', '10.00');

    $qrisId = $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/pos/checkout', $payload)->assertCreated()->json('data.id');
    $qrisPaymentKey = (string) Str::uuid();
    $this->withHeader('Idempotency-Key', $qrisPaymentKey)
        ->postJson("/api/v1/pos/transactions/{$qrisId}/pay-manual", [
            'method' => 'qris', 'reference' => 'QRIS-F8',
        ])->assertOk()->assertJsonPath('data.confirmed_by.id', $staffOne->id);

    $transferId = $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/pos/checkout', $payload)->assertCreated()->json('data.id');
    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/pos/transactions/{$transferId}/pay-manual", [
            'method' => 'transfer', 'reference' => 'TRF-F8',
        ])->assertOk()->assertJsonPath('data.confirmed_by.id', $staffOne->id);

    TenantContext::set($owner->tenant);
    expect(PosTransaction::whereIn('id', [$cashId, $qrisId, $transferId])->where('cashier_id', $staffOne->id)->count())->toBe(3)
        ->and(StockMovement::whereIn('reference_id', [$cashId, $qrisId, $transferId])->where('user_id', $staffOne->id)->count())->toBe(3)
        ->and(PosPayment::whereIn('pos_transaction_id', [$qrisId, $transferId])->where('confirmed_by', $staffOne->id)->count())->toBe(2);

    Sanctum::actingAs($staffTwo);
    $this->withHeader('Idempotency-Key', $cashKey)
        ->postJson('/api/v1/pos/checkout', $payload)
        ->assertConflict()
        ->assertJsonPath('error_code', 'IDEMPOTENCY_CONFLICT')
        ->assertJsonMissingPath('data.id');
    $this->getJson("/api/v1/pos/transactions/{$cashId}/status")->assertNotFound();
    $this->postJson("/api/v1/pos/transactions/{$cashId}/pay-cash", [
        'cash_received' => '100.00',
    ])->assertNotFound();

    $staffTwoId = $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/pos/checkout', $payload)->assertCreated()->json('data.id');

    TenantContext::set($owner->tenant);
    $forgedOwnerActor = clone $staffOne;
    $forgedOwnerActor->forceFill(['role' => UserRole::Owner]);
    expect(fn () => app(PayCashAction::class)->execute($staffTwoId, '100.00', $forgedOwnerActor))
        ->toThrow(ModelNotFoundException::class);

    $this->withHeader('Idempotency-Key', $qrisPaymentKey)
        ->postJson("/api/v1/pos/transactions/{$staffTwoId}/pay-manual", [
            'method' => 'qris', 'reference' => 'QRIS-F8',
        ])->assertConflict()->assertJsonPath('error_code', 'IDEMPOTENCY_CONFLICT');

    Sanctum::actingAs($staffOne);
    $this->postJson("/api/v1/pos/transactions/{$cashId}/void", [
        'reason' => 'Staff tidak boleh void',
    ])->assertForbidden();

    $staffOneInvoice = PosTransaction::withoutGlobalScopes()->findOrFail($cashId)->invoice_number;
    $staffTwoInvoice = PosTransaction::withoutGlobalScopes()->findOrFail($staffTwoId)->invoice_number;
    auth()->forgetGuards();
    $this->flushSession();
    $this->actingAs($staffOne, 'web')
        ->get('/app/pos-transactions')
        ->assertOk()
        ->assertSee($staffOneInvoice)
        ->assertDontSee($staffTwoInvoice)
        ->assertDontSee('window.print()', false);
    $this->get("/app/pos-transactions/{$staffTwoId}")->assertNotFound();
});
