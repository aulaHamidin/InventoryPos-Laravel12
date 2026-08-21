<?php

use App\Enums\MovementClass;
use App\Filament\Pages\AnalyticsSettings;
use App\Filament\Resources\ItemResource;
use App\Filament\Widgets\AnalyticsClassInsightWidget;
use App\Filament\Widgets\AnalyticsOperationalSummaryWidget;
use App\Filament\Widgets\ShoppingRecommendationWidget;
use App\Services\TenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('installs the F7 analytics columns enum and indexes', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem();

    expect(Schema::hasColumn('items', 'analytics_calculated_at'))->toBeTrue()
        ->and($item->refresh()->movement_class)->toBe(MovementClass::Unclassified);

    $itemIndexes = collect(DB::select('SHOW INDEX FROM items'))->pluck('Key_name')->all();
    $movementIndexes = collect(DB::select('SHOW INDEX FROM item_stock_movements'))->pluck('Key_name')->all();
    expect($itemIndexes)->toContain('idx_items_tenant_active_movement_class')
        ->and($movementIndexes)->toContain('idx_movements_tenant_item_type_created')
        ->and($owner)->not->toBeNull();
});

it('renders owner analytics settings explicit preview and dashboard states', function () {
    [, $owner] = makeTenantUser();
    makeInventoryItem();

    $this->actingAs($owner)->get(AnalyticsSettings::getUrl())
        ->assertOk()->assertSee('Pengaturan Analytics')->assertSee('Simpan Pengaturan');
    $this->actingAs($owner)->get(ItemResource::getUrl('index'))
        ->assertOk()->assertSee('Hitung Preview')->assertSee('Mode Threshold');

    TenantContext::set($owner->tenant);
    Livewire::actingAs($owner)->test(AnalyticsSettings::class)
        ->fillForm(['dead_stock_days' => 0])
        ->call('save')
        ->assertHasNoFormErrors();

    TenantContext::set($owner->tenant);
    Livewire::actingAs($owner)->test(ShoppingRecommendationWidget::class)->assertOk();
    TenantContext::set($owner->tenant);
    Livewire::actingAs($owner)->test(AnalyticsClassInsightWidget::class)->assertOk();
    TenantContext::set($owner->tenant);
    Livewire::actingAs($owner)->test(AnalyticsOperationalSummaryWidget::class)
        ->assertOk()->assertDontSee('Analytics tidak tersedia');
});

it('locks the analytics schedule metadata', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($scheduled): bool => str_contains((string) $scheduled->command, 'analytics:recalculate'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('15 0 * * *')
        ->and($event->timezone)->toBe('Asia/Jakarta')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(180)
        ->and($event->onOneServer)->toBeTrue();
});

it('keeps analytics dashboard query count constant as item volume grows', function () {
    [, $owner] = makeTenantUser();
    makeInventoryItem();
    $method = new ReflectionMethod(AnalyticsClassInsightWidget::class, 'getStats');
    $widget = app(AnalyticsClassInsightWidget::class);

    TenantContext::set($owner->tenant);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $smallStats = $method->invoke($widget);
    $smallCount = count(DB::getQueryLog());

    foreach (range(1, 20) as $index) {
        makeInventoryItem(['nama' => "Volume {$index}"]);
    }
    DB::flushQueryLog();
    TenantContext::set($owner->tenant);
    $largeStats = $method->invoke($widget);
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount)
        ->and($largeCount)->toBeLessThanOrEqual(5)
        ->and(json_encode([$smallStats, $largeStats]))->not->toContain('Rp');
});
