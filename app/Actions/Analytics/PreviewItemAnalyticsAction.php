<?php

namespace App\Actions\Analytics;

use App\Data\AnalyticsCalculationResult;
use App\Models\Item;
use App\Support\AnalyticsClock;
use App\Support\OwnershipGuard;
use Illuminate\Validation\ValidationException;

class PreviewItemAnalyticsAction
{
    public function __construct(
        private readonly CalculateItemAnalyticsAction $calculate,
        private readonly AnalyticsClock $clock,
    ) {}

    public function execute(int $itemId, int $leadTimeDays, int $safetyStockDays): AnalyticsCalculationResult
    {
        if ($leadTimeDays < 0 || $safetyStockDays < 0) {
            throw ValidationException::withMessages([
                'lead_time_days' => ['Lead time dan safety stock tidak boleh negatif.'],
            ]);
        }

        /** @var Item $item */
        $item = OwnershipGuard::validate(Item::class, $itemId);

        return $this->calculate->execute($item, $this->clock->now(), $leadTimeDays, $safetyStockDays);
    }
}
