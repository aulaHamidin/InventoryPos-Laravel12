<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class AnalyticsCalculationInput
{
    public function __construct(
        public CarbonImmutable $asOf,
        public CarbonImmutable $itemCreatedAt,
        public int $deadStockDays,
        public int $grossSale30,
        public int $saleVoid30,
        public int $customerReturn30,
        public int $grossSaleDead,
        public int $saleVoidDead,
        public int $customerReturnDead,
        public int $effectiveLeadTimeDays,
        public string $leadTimeSource,
        public int $safetyStockDays,
    ) {}
}
