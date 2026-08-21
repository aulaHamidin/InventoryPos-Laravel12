<?php

namespace App\Data;

use App\Enums\MovementClass;
use App\Support\AnalyticsClock;
use Carbon\CarbonImmutable;

final readonly class AnalyticsCalculationResult
{
    public function __construct(
        public bool $eligible,
        public CarbonImmutable $eligibleAt,
        public CarbonImmutable $windowStart,
        public CarbonImmutable $windowEnd,
        public int $grossSaleQty,
        public int $saleVoidQty,
        public int $customerReturnQty,
        public int $netDemandQty,
        public string $averageDailyOut,
        public string $leadTimeSource,
        public int $effectiveLeadTimeDays,
        public int $safetyStockDays,
        public int $deadStockDays,
        public ?CarbonImmutable $deadWindowStart,
        public int $deadNetDemandQty,
        public ?int $recommendedThreshold,
        public MovementClass $movementClass,
        public ?CarbonImmutable $calculatedAt,
    ) {}

    public function toApiArray(int $itemId, string $thresholdMode): array
    {
        $business = fn (CarbonImmutable $value): string => AnalyticsClock::business($value)->toIso8601String();

        return [
            'item_id' => $itemId,
            'threshold_mode' => $thresholdMode,
            'window' => [
                'timezone' => AnalyticsClock::BUSINESS_TIMEZONE,
                'start_inclusive' => $business($this->windowStart),
                'end_exclusive' => $business($this->windowEnd),
            ],
            'history' => [
                'eligible' => $this->eligible,
                'eligible_at' => $business($this->eligibleAt),
            ],
            'gross_sale_qty' => $this->grossSaleQty,
            'sale_void_qty' => $this->saleVoidQty,
            'customer_return_qty' => $this->customerReturnQty,
            'reversal_qty' => $this->saleVoidQty + $this->customerReturnQty,
            'net_demand_qty' => $this->netDemandQty,
            'avg_daily_out' => $this->averageDailyOut,
            'lead_time' => [
                'source' => $this->leadTimeSource,
                'effective_days' => $this->effectiveLeadTimeDays,
            ],
            'safety_stock_days' => $this->safetyStockDays,
            'dead_window' => [
                'days' => $this->deadStockDays,
                'start_inclusive' => $this->deadWindowStart ? $business($this->deadWindowStart) : null,
                'end_exclusive' => $business($this->windowEnd),
                'net_demand_qty' => $this->deadNetDemandQty,
            ],
            'recommended_threshold' => $this->recommendedThreshold,
            'movement_class' => $this->movementClass->value,
            'calculated_at' => $this->calculatedAt ? $business($this->calculatedAt) : null,
        ];
    }
}
