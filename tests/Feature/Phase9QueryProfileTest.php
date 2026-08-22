<?php

use App\Actions\Analytics\RecalculateItemAnalyticsAction;
use App\Filament\Widgets\AnalyticsClassInsightWidget;
use App\Filament\Widgets\AnalyticsOperationalSummaryWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\PosPaymentMethodSummary;
use App\Filament\Widgets\ShoppingRecommendationWidget;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function insertProfileItems(int $tenantId, int $categoryId, int $from, int $to): void
{
    $now = now();
    foreach (array_chunk(range($from, $to), 500) as $numbers) {
        DB::table('items')->insert(array_map(fn (int $number): array => [
            'tenant_id' => $tenantId,
            'category_id' => $categoryId,
            'rack_id' => null,
            'kode' => sprintf('PROFILE-%06d', $number),
            'barcode' => sprintf('899800%06d', $number),
            'nama' => sprintf('Profile Item %06d', $number),
            'satuan' => 'Pcs',
            'harga_beli' => '50.00',
            'average_cost' => '50.00',
            'harga_jual' => '100.00',
            'stok_saat_ini' => 100,
            'stok_minimal' => 5,
            'threshold_mode' => 'manual',
            'lead_time_days' => 0,
            'safety_stock_days' => 0,
            'exp_date' => null,
            'movement_class' => 'unclassified',
            'analytics_calculated_at' => null,
            'is_active' => true,
            'created_at' => $now->copy()->subDays(31),
            'updated_at' => $now,
            'deleted_at' => null,
        ], $numbers));
    }
}

function profiledQueries(Closure $operation): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $operation();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $signatures = collect($queries)
        ->map(function (array $query): string {
            $sql = strtolower(trim((string) preg_replace('/\s+/', ' ', $query['query'])));

            return (string) preg_replace(["/'(?:''|[^'])*'/", '/\b\d+(?:\.\d+)?\b/'], '?', $sql);
        })
        ->unique()
        ->sort()
        ->values()
        ->all();

    return [
        'count' => count($queries),
        'duration_ms' => round((float) collect($queries)->sum('time'), 3),
        'normalized_sql' => $signatures,
    ];
}

function widgetStats(string $widgetClass): void
{
    $method = new ReflectionMethod($widgetClass, 'getStats');
    $method->invoke(app($widgetClass));
}

it('keeps F9A API and dashboard query counts constant at 200 and 2000 items', function () {
    [$tenant, $owner] = makeTenantUser();
    $seed = makeInventoryItem(['kode' => 'PROFILE-SEED', 'barcode' => '899800000000']);
    DB::table('items')->where('id', $seed->id)->delete();
    insertProfileItems($tenant->id, $seed->category_id, 1, 200);

    $baseline = json_decode(file_get_contents(base_path('tests/Performance/query-baseline-f9a.json')), true, flags: JSON_THROW_ON_ERROR);
    $measure = function () use ($owner, $tenant): array {
        $profileTenant = $tenant->fresh();
        $profileOwner = $owner->fresh();
        TenantContext::set($profileTenant);
        Sanctum::actingAs($profileOwner);
        $this->actingAs($profileOwner);

        Queue::fake();
        $itemId = (int) DB::table('items')->orderBy('id')->value('id');
        $checkout = null;
        $checkoutProfile = profiledQueries(function () use ($itemId, &$checkout): void {
            $checkout = $this->withHeader('Idempotency-Key', (string) Str::uuid())
                ->postJson('/api/v1/pos/checkout', [
                    'items' => [['item_id' => $itemId, 'qty' => 1, 'discount_amount' => '0.00']],
                ])->assertCreated();
        });
        $pending = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/pos/checkout', [
                'items' => [['item_id' => $itemId, 'qty' => 1, 'discount_amount' => '0.00']],
            ])->assertCreated();

        return [
            'item_index' => profiledQueries(fn () => $this->getJson('/api/v1/items?per_page=100')->assertOk()),
            'item_search' => profiledQueries(fn () => $this->getJson('/api/v1/items?search=PROFILE-000001')->assertOk()),
            'item_scan' => profiledQueries(fn () => $this->getJson('/api/v1/items/scan/899800000001')->assertOk()),
            'checkout' => $checkoutProfile,
            'payment' => profiledQueries(fn () => $this->postJson('/api/v1/pos/transactions/'.$pending->json('data.id').'/pay-cash', [
                'cash_received' => '100.00',
            ])->assertOk()),
            'analytics' => profiledQueries(fn () => app(RecalculateItemAnalyticsAction::class)->execute($itemId, reason: 'f9a_query_profile')),
            'low_stock_widget' => profiledQueries(fn () => widgetStats(LowStockWidget::class)),
            'analytics_class_widget' => profiledQueries(fn () => widgetStats(AnalyticsClassInsightWidget::class)),
            'analytics_operational_widget' => profiledQueries(fn () => widgetStats(AnalyticsOperationalSummaryWidget::class)),
            'shopping_recommendation_widget' => profiledQueries(fn () => widgetStats(ShoppingRecommendationWidget::class)),
            'payment_method_widget' => profiledQueries(fn () => widgetStats(PosPaymentMethodSummary::class)),
        ];
    };

    $small = $measure();
    insertProfileItems($tenant->id, $seed->category_id, 201, 2000);
    $large = $measure();

    foreach ($large as $operation => $profile) {
        expect($profile['count'], "{$operation} query growth")->toBeLessThanOrEqual($small[$operation]['count']);
        expect($profile['count'], "{$operation} query budget")->toBeLessThanOrEqual($baseline['max_queries'][$operation]);
        expect($profile['normalized_sql'], "{$operation} SQL shape drift")->toBe($small[$operation]['normalized_sql']);
    }

    $path = storage_path('framework/testing/f9a-query-profile.json');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode([
        'version' => 1,
        'database' => config('database.default'),
        'datasets' => ['small_items' => 200, 'large_items' => 2000],
        'small' => $small,
        'large' => $large,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
});
