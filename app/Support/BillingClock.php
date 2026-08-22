<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class BillingClock
{
    public const BUSINESS_TIMEZONE = 'Asia/Jakarta';

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::BUSINESS_TIMEZONE)->startOfSecond();
    }

    public static function business(CarbonImmutable $instant): CarbonImmutable
    {
        return $instant->setTimezone(self::BUSINESS_TIMEZONE);
    }

    public static function storage(CarbonImmutable $instant): CarbonImmutable
    {
        return $instant->setTimezone('UTC');
    }
}
