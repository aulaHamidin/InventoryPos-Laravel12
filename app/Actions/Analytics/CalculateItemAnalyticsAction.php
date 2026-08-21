<?php

namespace App\Actions\Analytics;

use App\Data\AnalyticsCalculationInput;
use App\Data\AnalyticsCalculationResult;
use App\Exceptions\ApiProblemException;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\TenantContext;
use App\Support\AnalyticsCalculator;
use App\Support\AnalyticsClock;
use Carbon\CarbonImmutable;

class CalculateItemAnalyticsAction
{
    public function __construct(private readonly AnalyticsCalculator $calculator) {}

    public function execute(
        Item $item,
        CarbonImmutable $asOf,
        ?int $fallbackLeadTimeDays = null,
        ?int $safetyStockDays = null,
    ): AnalyticsCalculationResult {
        if ((int) $item->tenant_id !== TenantContext::id()) {
            throw new ApiProblemException('Resource tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $asOf = AnalyticsClock::business($asOf);
        $windowStart = $asOf->subHours(30 * 24);
        $deadDays = (int) TenantContext::get()->dead_stock_days;
        $deadStart = $deadDays > 0 ? $asOf->subHours($deadDays * 24) : $asOf;

        $storageStart30 = AnalyticsClock::storage($windowStart);
        $storageStartDead = AnalyticsClock::storage($deadStart);
        $storageEnd = AnalyticsClock::storage($asOf);
        $queryStart = $storageStartDead->lessThan($storageStart30)
            ? $storageStartDead
            : $storageStart30;

        $totals = StockMovement::query()
            ->where('item_id', $item->getKey())
            ->where('created_at', '>=', $queryStart)
            ->where('created_at', '<', $storageEnd)
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN movement_type = 'sale' AND created_at >= ? AND created_at < ? THEN qty ELSE 0 END), 0) AS gross_sale_30,
                COALESCE(SUM(CASE WHEN movement_type = 'sale_void' AND created_at >= ? AND created_at < ? THEN qty ELSE 0 END), 0) AS sale_void_30,
                COALESCE(SUM(CASE WHEN movement_type = 'customer_return' AND created_at >= ? AND created_at < ? THEN qty ELSE 0 END), 0) AS customer_return_30,
                COALESCE(SUM(CASE WHEN movement_type = 'sale' AND created_at >= ? AND created_at < ? THEN qty ELSE 0 END), 0) AS gross_sale_dead,
                COALESCE(SUM(CASE WHEN movement_type = 'sale_void' AND created_at >= ? AND created_at < ? THEN qty ELSE 0 END), 0) AS sale_void_dead,
                COALESCE(SUM(CASE WHEN movement_type = 'customer_return' AND created_at >= ? AND created_at < ? THEN qty ELSE 0 END), 0) AS customer_return_dead",
                [
                    $storageStart30, $storageEnd,
                    $storageStart30, $storageEnd,
                    $storageStart30, $storageEnd,
                    $storageStartDead, $storageEnd,
                    $storageStartDead, $storageEnd,
                    $storageStartDead, $storageEnd,
                ],
            )->first();

        [$leadTime, $leadSource] = $this->effectiveLeadTime(
            $item,
            $fallbackLeadTimeDays ?? (int) $item->lead_time_days,
        );

        return $this->calculator->calculate(new AnalyticsCalculationInput(
            asOf: $asOf,
            itemCreatedAt: CarbonImmutable::instance($item->created_at),
            deadStockDays: $deadDays,
            grossSale30: (int) $totals->gross_sale_30,
            saleVoid30: (int) $totals->sale_void_30,
            customerReturn30: (int) $totals->customer_return_30,
            grossSaleDead: $deadDays > 0 ? (int) $totals->gross_sale_dead : 0,
            saleVoidDead: $deadDays > 0 ? (int) $totals->sale_void_dead : 0,
            customerReturnDead: $deadDays > 0 ? (int) $totals->customer_return_dead : 0,
            effectiveLeadTimeDays: $leadTime,
            leadTimeSource: $leadSource,
            safetyStockDays: $safetyStockDays ?? (int) $item->safety_stock_days,
        ));
    }

    private function effectiveLeadTime(Item $item, int $fallback): array
    {
        $preferred = ItemSupplier::withoutGlobalScopes()
            ->where('item_id', $item->getKey())
            ->where('is_preferred', true)
            ->get();

        if ($preferred->count() > 1) {
            throw new ApiProblemException('Relasi preferred supplier tidak valid.', 'NOT_FOUND', 404);
        }

        $link = $preferred->first();
        if ($link === null) {
            return [$fallback, 'item'];
        }

        $supplier = Supplier::withoutGlobalScopes()->find($link->supplier_id);
        if ((int) $link->tenant_id !== (int) $item->tenant_id
            || $supplier === null
            || (int) $supplier->tenant_id !== (int) $item->tenant_id) {
            throw new ApiProblemException('Relasi preferred supplier tidak valid.', 'NOT_FOUND', 404);
        }

        return $link->lead_time_days !== null
            ? [(int) $link->lead_time_days, 'preferred_supplier']
            : [$fallback, 'item'];
    }
}
