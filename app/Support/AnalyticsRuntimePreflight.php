<?php

namespace App\Support;

use RuntimeException;

final class AnalyticsRuntimePreflight
{
    /** @var list<string> */
    private const DISTRIBUTED_CACHE_DRIVERS = ['redis'];

    public function assertProductionReady(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $store = (string) config('cache.default');
        $driver = (string) config("cache.stores.{$store}.driver");
        if (! in_array($driver, self::DISTRIBUTED_CACHE_DRIVERS, true)) {
            throw new RuntimeException(
                "Analytics membutuhkan distributed lock di production; CACHE_STORE={$store} ({$driver}) tidak didukung.",
            );
        }
    }
}
