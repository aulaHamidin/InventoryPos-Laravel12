<?php

namespace App\Support;

use App\Enums\BillingInterval;
use Carbon\CarbonImmutable;

final class BillingPeriodCalculator
{
    public function end(CarbonImmutable $start, BillingInterval $interval): CarbonImmutable
    {
        return match ($interval) {
            BillingInterval::Monthly => $start->addMonthNoOverflow(),
            BillingInterval::Yearly => $start->addYearNoOverflow(),
        };
    }
}
