<?php

namespace App\Support;

use App\Data\AnalyticsCalculationInput;
use App\Data\AnalyticsCalculationResult;
use App\Enums\MovementClass;
use InvalidArgumentException;
use OverflowException;

final class AnalyticsCalculator
{
    private const VELOCITY_HOURS = 30 * 24;

    public function calculate(AnalyticsCalculationInput $input): AnalyticsCalculationResult
    {
        if ($input->asOf->getTimezone()->getName() !== AnalyticsClock::BUSINESS_TIMEZONE) {
            throw new InvalidArgumentException('Analytics as_of must use Asia/Jakarta.');
        }

        foreach ([
            $input->deadStockDays,
            $input->grossSale30,
            $input->saleVoid30,
            $input->customerReturn30,
            $input->grossSaleDead,
            $input->saleVoidDead,
            $input->customerReturnDead,
            $input->effectiveLeadTimeDays,
            $input->safetyStockDays,
        ] as $value) {
            if ($value < 0) {
                throw new InvalidArgumentException('Analytics inputs cannot be negative.');
            }
        }

        $asOf = AnalyticsClock::business($input->asOf);
        $createdAt = AnalyticsClock::business($input->itemCreatedAt);
        $eligibleAt = $createdAt->addHours(self::VELOCITY_HOURS);
        $windowStart = $asOf->subHours(self::VELOCITY_HOURS);
        $eligible = $asOf->greaterThanOrEqualTo($eligibleAt);

        $net30 = self::net($input->grossSale30, $input->saleVoid30, $input->customerReturn30);
        $netDead = self::net($input->grossSaleDead, $input->saleVoidDead, $input->customerReturnDead);
        $average = bcdiv((string) $net30, '30', 6);
        $deadWindowStart = $input->deadStockDays > 0
            ? $asOf->subHours($input->deadStockDays * 24)
            : null;

        if (! $eligible) {
            return new AnalyticsCalculationResult(
                false,
                $eligibleAt,
                $windowStart,
                $asOf,
                $input->grossSale30,
                $input->saleVoid30,
                $input->customerReturn30,
                $net30,
                $average,
                $input->leadTimeSource,
                $input->effectiveLeadTimeDays,
                $input->safetyStockDays,
                $input->deadStockDays,
                $deadWindowStart,
                $netDead,
                null,
                MovementClass::Unclassified,
                null,
            );
        }

        $deadEligibleAt = $createdAt->addHours($input->deadStockDays * 24);
        $isDead = $input->deadStockDays > 0
            && $asOf->greaterThanOrEqualTo($deadEligibleAt)
            && $netDead === 0;

        $movementClass = match (true) {
            $isDead => MovementClass::Dead,
            $net30 >= 30 => MovementClass::Fast,
            $net30 >= 8 => MovementClass::Normal,
            default => MovementClass::Slow,
        };

        $days = $input->effectiveLeadTimeDays + $input->safetyStockDays;
        $numerator = bcmul((string) $net30, (string) $days, 0);
        $threshold = bcdiv(bcadd($numerator, '29', 0), '30', 0);
        if (bccomp($threshold, (string) PHP_INT_MAX, 0) > 0) {
            throw new OverflowException('Calculated threshold exceeds supported integer range.');
        }

        return new AnalyticsCalculationResult(
            true,
            $eligibleAt,
            $windowStart,
            $asOf,
            $input->grossSale30,
            $input->saleVoid30,
            $input->customerReturn30,
            $net30,
            $average,
            $input->leadTimeSource,
            $input->effectiveLeadTimeDays,
            $input->safetyStockDays,
            $input->deadStockDays,
            $deadWindowStart,
            $netDead,
            (int) $threshold,
            $movementClass,
            $asOf,
        );
    }

    private static function net(int $sales, int $voids, int $returns): int
    {
        return max(0, $sales - $voids - $returns);
    }
}
