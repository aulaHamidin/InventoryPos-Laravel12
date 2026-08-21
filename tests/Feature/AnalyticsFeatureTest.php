<?php

use App\Actions\Analytics\RecalculateItemAnalyticsAction;
use App\Actions\Analytics\UpdateTenantAnalyticsSettingsAction;
use App\Actions\Inventory\DeleteItemSupplierAction;
use App\Actions\Inventory\SetPreferredSupplierAction;
use App\Actions\Inventory\UnsetPreferredSupplierAction;
use App\Actions\Inventory\UpdateItemAction;
use App\Actions\Inventory\UpsertItemSupplierAction;
use App\Enums\MovementClass;
use App\Enums\UserRole;
use App\Events\ItemAnalyticsRecalculationRequested;
use App\Events\TenantAnalyticsRecalculationRequested;
use App\Jobs\RecalculateItemAnalyticsJob;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AnalyticsClock;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

function ageAnalyticsItem(Item $item, CarbonImmutable $asOf, int $days = 31): void
{
    DB::table('items')->where('id', $item->id)->update([
        'created_at' => AnalyticsClock::storage($asOf->subDays($days)),
    ]);
}

function analyticsMovement(Item $item, User $actor, string $type, int $qty, CarbonImmutable $at): void
{
    $movement = StockMovement::create([
        'item_id' => $item->id,
        'user_id' => $actor->id,
        'movement_type' => $type,
        'qty' => $qty,
        'direction' => $type === 'sale' ? 'out' : 'in',
        'harga_satuan' => '1.00',
    ]);
    DB::table('item_stock_movements')->where('id', $movement->id)->update([
        'created_at' => AnalyticsClock::storage($at),
    ]);
}

it('applies the exact smart threshold API contract and avoids duplicate business audit', function () {
    [, $owner] = makeTenantUser(tenantAttributes: ['dead_stock_days' => 0]);
    $item = makeInventoryItem(['stok_minimal' => 99, 'threshold_mode' => 'manual']);
    $asOf = CarbonImmutable::now(AnalyticsClock::BUSINESS_TIMEZONE);
    ageAnalyticsItem($item, $asOf);
    analyticsMovement($item, $owner, 'sale', 29, $asOf->subDay());
    Sanctum::actingAs($owner);

    $payload = [
        'threshold_mode' => 'auto_velocity',
        'lead_time_days' => 2,
        'safety_stock_days' => 1,
    ];
    $response = $this->postJson("/api/v1/items/{$item->id}/smart-threshold", $payload)
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.threshold_mode', 'auto_velocity')
        ->assertJsonPath('data.window.timezone', 'Asia/Jakarta')
        ->assertJsonPath('data.net_demand_qty', 29)
        ->assertJsonPath('data.avg_daily_out', '0.966666')
        ->assertJsonPath('data.recommended_threshold', 3)
        ->assertJsonPath('data.movement_class', 'normal');

    expect($response->json('data.calculated_at'))->toEndWith('+07:00')
        ->and($item->fresh()->threshold_mode)->toBe('auto_velocity')
        ->and($item->fresh()->movement_class)->toBe(MovementClass::Normal)
        ->and($item->fresh()->stok_minimal)->toBe(3);

    TenantContext::set($owner->tenant);
    expect(AuditLog::where('action', 'analytics.smart_threshold_applied')->count())->toBe(1);

    $this->postJson("/api/v1/items/{$item->id}/smart-threshold", $payload)->assertOk();
    TenantContext::set($owner->tenant);
    expect(AuditLog::where('action', 'analytics.smart_threshold_applied')->count())->toBe(1);
});

it('returns insufficient history with zero mutation and rejects extra fields', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_minimal' => 7, 'threshold_mode' => 'manual']);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/items/{$item->id}/smart-threshold", [
        'threshold_mode' => 'auto_velocity',
        'lead_time_days' => 1,
        'safety_stock_days' => 1,
    ])->assertStatus(422)
        ->assertJsonPath('error_code', 'INSUFFICIENT_HISTORY')
        ->assertJsonPath('errors.eligible_at', fn (string $value): bool => str_ends_with($value, '+07:00'));

    TenantContext::set($owner->tenant);
    expect($item->fresh()->threshold_mode)->toBe('manual')
        ->and($item->fresh()->stok_minimal)->toBe(7)
        ->and($item->fresh()->analytics_calculated_at)->toBeNull()
        ->and(AuditLog::where('action', 'analytics.smart_threshold_applied')->count())->toBe(0);

    $this->postJson("/api/v1/items/{$item->id}/smart-threshold", [
        'threshold_mode' => 'auto_velocity',
        'lead_time_days' => 1,
        'safety_stock_days' => 1,
        'tenant_id' => 999,
    ])->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');
});

it('denies Staff and hides cross tenant smart threshold targets', function () {
    [$tenantA, $ownerA] = makeTenantUser();
    $itemA = makeInventoryItem();
    TenantContext::run($tenantA, function () use (&$staff): void {
        $staff = User::create([
            'name' => 'Threshold Staff', 'email' => 'threshold-staff@example.test',
            'no_hp' => '085555555555', 'password' => 'password', 'role' => UserRole::Staff,
        ]);
    });
    [, $ownerB] = makeTenantUser();
    $itemB = makeInventoryItem();
    $payload = [
        'threshold_mode' => 'auto_velocity',
        'lead_time_days' => 1,
        'safety_stock_days' => 1,
    ];

    TenantContext::set($tenantA);
    Sanctum::actingAs($staff);
    $this->postJson("/api/v1/items/{$itemA->id}/smart-threshold", $payload)
        ->assertForbidden()->assertJsonPath('error_code', 'FORBIDDEN');

    TenantContext::set($tenantA);
    Sanctum::actingAs($ownerA);
    $this->postJson("/api/v1/items/{$itemB->id}/smart-threshold", $payload)
        ->assertNotFound()->assertJsonPath('error_code', 'NOT_FOUND');

    expect($ownerB)->not->toBeNull();
});

it('keeps manual thresholds while classifying and uses preferred lead time zero', function () {
    [, $owner] = makeTenantUser(tenantAttributes: ['dead_stock_days' => 0]);
    $item = makeInventoryItem(['stok_minimal' => 17, 'threshold_mode' => 'manual', 'lead_time_days' => 9]);
    $supplier = Supplier::create(['nama' => 'Lead Zero']);
    ItemSupplier::create([
        'item_id' => $item->id,
        'supplier_id' => $supplier->id,
        'lead_time_days' => 0,
        'is_preferred' => true,
    ]);
    $asOf = CarbonImmutable::now(AnalyticsClock::BUSINESS_TIMEZONE);
    ageAnalyticsItem($item, $asOf);
    analyticsMovement($item, $owner, 'sale', 30, $asOf->subHour());

    $result = app(RecalculateItemAnalyticsAction::class)->execute($item->id, $asOf, 'test');

    expect($result?->leadTimeSource)->toBe('preferred_supplier')
        ->and($result?->effectiveLeadTimeDays)->toBe(0)
        ->and($item->fresh()->movement_class)->toBe(MovementClass::Fast)
        ->and($item->fresh()->stok_minimal)->toBe(17)
        ->and($item->fresh()->analytics_calculated_at)->not->toBeNull();

    $audit = AuditLog::where('action', 'analytics.recalculated')->firstOrFail();
    expect($audit->actor_type)->toBe('system')->and($audit->actor_id)->toBeNull();
});

it('fails closed for a corrupt cross tenant preferred supplier link', function () {
    [$tenantA, $ownerA] = makeTenantUser(tenantAttributes: ['dead_stock_days' => 0]);
    $item = makeInventoryItem();
    $asOf = CarbonImmutable::now(AnalyticsClock::BUSINESS_TIMEZONE);
    ageAnalyticsItem($item, $asOf);

    [, $ownerB] = makeTenantUser();
    $foreignSupplier = Supplier::create(['nama' => 'Foreign']);
    TenantContext::set($tenantA);
    ItemSupplier::create([
        'item_id' => $item->id,
        'supplier_id' => $foreignSupplier->id,
        'lead_time_days' => 1,
        'is_preferred' => true,
    ]);
    Sanctum::actingAs($ownerA);

    $this->postJson("/api/v1/items/{$item->id}/smart-threshold", [
        'threshold_mode' => 'auto_velocity',
        'lead_time_days' => 1,
        'safety_stock_days' => 1,
    ])->assertNotFound()->assertJsonPath('error_code', 'NOT_FOUND');

    expect($ownerB)->not->toBeNull();
});

it('guards generic auto mode activation and emits item configuration triggers', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['threshold_mode' => 'manual']);

    expect(fn () => app(UpdateItemAction::class)->execute(
        $item->id,
        ['threshold_mode' => 'auto_velocity'],
        $owner,
    ))->toThrow(ValidationException::class);

    Event::fake([ItemAnalyticsRecalculationRequested::class]);
    app(UpdateItemAction::class)->execute($item->id, ['nama' => 'Tanpa Trigger'], $owner);
    Event::assertNotDispatched(ItemAnalyticsRecalculationRequested::class);

    Event::fake([ItemAnalyticsRecalculationRequested::class]);
    app(UpdateItemAction::class)->execute($item->id, ['lead_time_days' => 4], $owner);
    Event::assertDispatched(ItemAnalyticsRecalculationRequested::class, fn ($event): bool => $event->tenantId === TenantContext::id()
        && $event->itemIds === [$item->id]
        && $event->reason === 'item_configuration_changed');
});

it('covers the preferred supplier trigger lifecycle without irrelevant dispatches', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem();
    $supplier = Supplier::create(['nama' => 'Trigger Supplier']);
    Event::fake([ItemAnalyticsRecalculationRequested::class]);

    $link = app(UpsertItemSupplierAction::class)->execute(
        $item->id,
        $supplier->id,
        ['lead_time_days' => 5, 'is_preferred' => false],
        $owner,
    );
    Event::assertNotDispatched(ItemAnalyticsRecalculationRequested::class);

    app(SetPreferredSupplierAction::class)->execute($link->id, $owner);
    Event::assertDispatched(ItemAnalyticsRecalculationRequested::class, fn ($event): bool => $event->reason === 'preferred_supplier_set');

    app(UpsertItemSupplierAction::class)->execute(
        $item->id,
        $supplier->id,
        ['lead_time_days' => 6],
        $owner,
    );
    Event::assertDispatched(ItemAnalyticsRecalculationRequested::class, fn ($event): bool => $event->reason === 'preferred_supplier_lead_time_changed');

    app(UnsetPreferredSupplierAction::class)->execute($link->id, $owner);
    Event::assertDispatched(ItemAnalyticsRecalculationRequested::class, fn ($event): bool => $event->reason === 'preferred_supplier_unset');

    app(SetPreferredSupplierAction::class)->execute($link->id, $owner);
    app(DeleteItemSupplierAction::class)->execute($link->id, $owner);
    Event::assertDispatched(ItemAnalyticsRecalculationRequested::class, fn ($event): bool => $event->reason === 'preferred_supplier_deleted');
});

it('updates dead stock settings through the owner action and queues a tenant refresh', function () {
    [$tenant, $owner] = makeTenantUser(tenantAttributes: ['dead_stock_days' => 90]);
    Event::fake([TenantAnalyticsRecalculationRequested::class]);

    $updated = app(UpdateTenantAnalyticsSettingsAction::class)->execute(0, $owner);

    expect($updated->dead_stock_days)->toBe(0)
        ->and(AuditLog::where('action', 'tenant.analytics_settings_updated')->count())->toBe(1);
    Event::assertDispatched(TenantAnalyticsRecalculationRequested::class, fn ($event): bool => $event->tenantId === $tenant->id && $event->reason === 'dead_stock_days_changed');

    TenantContext::run($tenant, function () use (&$staff): void {
        $staff = User::create([
            'name' => 'Analytics Staff', 'email' => 'analytics-staff@example.test',
            'no_hp' => '084444444444', 'password' => 'password', 'role' => UserRole::Staff,
        ]);
    });
    expect(fn () => app(UpdateTenantAnalyticsSettingsAction::class)->execute(30, $staff))
        ->toThrow(AuthorizationException::class);
});

it('locks the item job uniqueness and retry contract', function () {
    $job = new RecalculateItemAnalyticsJob(11, 27);

    expect($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->uniqueId())->toBe('11:27')
        ->and($job->uniqueFor)->toBe(300)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([5, 30, 120])
        ->and($job->queue)->toBe('analytics');
});
