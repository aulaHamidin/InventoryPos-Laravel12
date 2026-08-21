<?php

namespace App\Actions\Analytics;

use App\Actions\Audit\RecordAuditAction;
use App\Data\AnalyticsCalculationResult;
use App\Enums\UserRole;
use App\Exceptions\ApiProblemException;
use App\Models\Item;
use App\Models\User;
use App\Support\AnalyticsClock;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplySmartThresholdAction
{
    public function __construct(
        private readonly CalculateItemAnalyticsAction $calculate,
        private readonly AnalyticsClock $clock,
        private readonly RecordAuditAction $audit,
    ) {}

    public function execute(
        int $itemId,
        int $leadTimeDays,
        int $safetyStockDays,
        User $actor,
        ?AuditContext $context = null,
    ): AnalyticsCalculationResult {
        OwnershipGuard::validate(User::class, $actor->getKey());
        OwnershipGuard::validate(Item::class, $itemId);
        if ($actor->role !== UserRole::Owner) {
            throw new AuthorizationException;
        }
        if ($leadTimeDays < 0 || $safetyStockDays < 0) {
            throw ValidationException::withMessages([
                'lead_time_days' => ['Lead time dan safety stock tidak boleh negatif.'],
            ]);
        }

        return DB::transaction(function () use ($itemId, $leadTimeDays, $safetyStockDays, $actor, $context): AnalyticsCalculationResult {
            $item = Item::whereKey($itemId)->lockForUpdate()->firstOrFail();
            if (! $item->is_active) {
                throw new ApiProblemException('Item tidak aktif.', 'ITEM_INACTIVE', 422);
            }

            $result = $this->calculate->execute(
                $item,
                $this->clock->now(),
                $leadTimeDays,
                $safetyStockDays,
            );
            if (! $result->eligible) {
                throw new ApiProblemException(
                    'Histori item belum mencapai 30 hari penuh.',
                    'INSUFFICIENT_HISTORY',
                    422,
                    ['eligible_at' => AnalyticsClock::business($result->eligibleAt)->toIso8601String()],
                );
            }

            $old = [
                'threshold_mode' => $item->threshold_mode,
                'lead_time_days' => (int) $item->lead_time_days,
                'safety_stock_days' => (int) $item->safety_stock_days,
                'stok_minimal' => (int) $item->stok_minimal,
                'movement_class' => $item->movement_class->value,
            ];
            $new = [
                'threshold_mode' => 'auto_velocity',
                'lead_time_days' => $leadTimeDays,
                'safety_stock_days' => $safetyStockDays,
                'stok_minimal' => $result->recommendedThreshold,
                'movement_class' => $result->movementClass,
                'analytics_calculated_at' => AnalyticsClock::storage($result->calculatedAt),
            ];
            $item->forceFill($new)->save();
            $item->refresh();

            $newBusiness = [
                'threshold_mode' => $item->threshold_mode,
                'lead_time_days' => (int) $item->lead_time_days,
                'safety_stock_days' => (int) $item->safety_stock_days,
                'stok_minimal' => (int) $item->stok_minimal,
                'movement_class' => $item->movement_class->value,
            ];
            if ($old !== $newBusiness) {
                $this->audit->execute(
                    'analytics.smart_threshold_applied',
                    $actor,
                    $item,
                    oldValues: $old,
                    newValues: $newBusiness,
                    context: $context,
                    metadata: ['as_of' => AnalyticsClock::business($result->windowEnd)->toIso8601String()],
                );
            }

            return $result;
        }, 3);
    }
}
