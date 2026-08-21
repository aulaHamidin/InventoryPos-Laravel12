<?php

namespace App\Actions\Analytics;

use App\Actions\Audit\RecordAuditAction;
use App\Data\AnalyticsCalculationResult;
use App\Enums\MovementClass;
use App\Models\Item;
use App\Support\AnalyticsClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecalculateItemAnalyticsAction
{
    public function __construct(
        private readonly CalculateItemAnalyticsAction $calculate,
        private readonly AnalyticsClock $clock,
        private readonly RecordAuditAction $audit,
    ) {}

    public function execute(
        int $itemId,
        ?CarbonImmutable $asOf = null,
        string $reason = 'event',
    ): ?AnalyticsCalculationResult {
        return DB::transaction(function () use ($itemId, $asOf, $reason): ?AnalyticsCalculationResult {
            $item = Item::whereKey($itemId)->lockForUpdate()->first();
            if ($item === null || ! $item->is_active) {
                return null;
            }

            $result = $this->calculate->execute($item, $asOf ?? $this->clock->now());
            $oldClass = $item->movement_class;
            $oldThreshold = (int) $item->stok_minimal;

            if (! $result->eligible) {
                $item->forceFill([
                    'movement_class' => MovementClass::Unclassified,
                    'analytics_calculated_at' => null,
                ])->save();
            } else {
                $values = [
                    'movement_class' => $result->movementClass,
                    'analytics_calculated_at' => AnalyticsClock::storage($result->calculatedAt),
                ];
                if ($item->threshold_mode === 'auto_velocity') {
                    $values['stok_minimal'] = $result->recommendedThreshold;
                }
                $item->forceFill($values)->save();
            }

            $item->refresh();
            $classChanged = $oldClass !== $item->movement_class;
            $thresholdChanged = $oldThreshold !== (int) $item->stok_minimal;
            if ($classChanged || $thresholdChanged) {
                $this->audit->execute(
                    'analytics.recalculated',
                    subject: $item,
                    oldValues: [
                        'movement_class' => $oldClass?->value ?? (string) $oldClass,
                        'stok_minimal' => $oldThreshold,
                    ],
                    newValues: [
                        'movement_class' => $item->movement_class->value,
                        'stok_minimal' => $item->stok_minimal,
                    ],
                    metadata: ['reason' => $reason, 'as_of' => $this->clockString($result)],
                );
            }

            return $result;
        }, 3);
    }

    private function clockString(AnalyticsCalculationResult $result): string
    {
        return AnalyticsClock::business($result->windowEnd)->toIso8601String();
    }
}
