<?php

namespace App\Console\Commands;

use App\Actions\Analytics\RecalculateItemAnalyticsAction;
use App\Jobs\RecalculateTenantAnalyticsJob;
use App\Models\Item;
use App\Models\Tenant;
use App\Services\TenantContext;
use App\Support\AnalyticsClock;
use App\Support\AnalyticsRuntimePreflight;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RecalculateAnalytics extends Command
{
    protected $signature = 'analytics:recalculate
        {--tenant= : Batasi ke ID tenant}
        {--sync : Hitung langsung tanpa queue}';

    protected $description = 'Antrekan atau jalankan kalkulasi analytics untuk seluruh item aktif';

    public function handle(
        AnalyticsRuntimePreflight $preflight,
        RecalculateItemAnalyticsAction $recalculate,
        AnalyticsClock $clock,
    ): int {
        $preflight->assertProductionReady();
        $tenantId = $this->option('tenant');
        $tenants = Tenant::query()
            ->when($tenantId !== null, fn ($query) => $query->whereKey((int) $tenantId))
            ->orderBy('id')
            ->get();

        if ($tenantId !== null && $tenants->isEmpty()) {
            $this->error('Tenant tidak ditemukan.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            if ($this->option('sync')) {
                TenantContext::run($tenant, function () use ($recalculate): void {
                    Item::query()->where('is_active', true)->orderBy('id')
                        ->chunkById(200, function ($items) use ($recalculate): void {
                            foreach ($items as $item) {
                                $recalculate->execute((int) $item->getKey(), reason: 'manual_sweep');
                            }
                        });
                });
            } else {
                RecalculateTenantAnalyticsJob::dispatch((int) $tenant->getKey(), 'daily_sweep');
            }
        }

        $at = $clock->now();
        Cache::put('analytics:last_sweep_at', $at->toIso8601String(), $at->addDays(3));
        $this->info(sprintf(
            'Analytics sweep %s untuk %d tenant.',
            $this->option('sync') ? 'dijalankan sinkron' : 'diantrekan',
            $tenants->count(),
        ));

        return self::SUCCESS;
    }
}
