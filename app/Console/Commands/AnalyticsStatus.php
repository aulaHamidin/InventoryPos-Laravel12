<?php

namespace App\Console\Commands;

use App\Enums\MovementClass;
use App\Models\Item;
use App\Models\Tenant;
use App\Services\TenantContext;
use App\Support\AnalyticsClock;
use App\Support\AnalyticsRuntimePreflight;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AnalyticsStatus extends Command
{
    protected $signature = 'analytics:status
        {--tenant= : Batasi ke ID tenant}
        {--fail-on-incomplete : Exit non-zero jika exit gate belum terpenuhi}';

    protected $description = 'Laporkan kesiapan backfill dan runtime analytics';

    public function handle(AnalyticsRuntimePreflight $preflight, AnalyticsClock $clock): int
    {
        $preflight->assertProductionReady();
        $asOf = $clock->now();
        $eligibleBefore = AnalyticsClock::storage($asOf->subHours(30 * 24));
        $tenantId = $this->option('tenant');
        $tenants = Tenant::query()
            ->when($tenantId !== null, fn ($query) => $query->whereKey((int) $tenantId))
            ->orderBy('id')
            ->get();

        if ($tenantId !== null && $tenants->isEmpty()) {
            $this->error('Tenant tidak ditemukan.');

            return self::FAILURE;
        }

        $totals = [
            'active' => 0,
            'eligible' => 0,
            'ineligible' => 0,
            'eligible_unclassified' => 0,
            'eligible_timestamp_null' => 0,
            'ineligible_timestamp_present' => 0,
        ];
        foreach ($tenants as $tenant) {
            $row = TenantContext::run($tenant, function () use ($eligibleBefore): array {
                $active = Item::query()->where('is_active', true);
                $eligible = (clone $active)->where('created_at', '<=', $eligibleBefore);

                return [
                    'active' => (clone $active)->count(),
                    'eligible' => (clone $eligible)->count(),
                    'ineligible' => (clone $active)->where('created_at', '>', $eligibleBefore)->count(),
                    'eligible_unclassified' => (clone $eligible)
                        ->where('movement_class', MovementClass::Unclassified->value)->count(),
                    'eligible_timestamp_null' => (clone $eligible)->whereNull('analytics_calculated_at')->count(),
                    'ineligible_timestamp_present' => (clone $active)
                        ->where('created_at', '>', $eligibleBefore)
                        ->whereNotNull('analytics_calculated_at')->count(),
                ];
            });
            foreach ($totals as $key => $unused) {
                $totals[$key] += $row[$key];
            }
        }

        $queueDepth = $this->queueDepth();
        $failed = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('queue', 'analytics')->count()
            : 0;
        $lastSweep = Cache::get('analytics:last_sweep_at');
        $lastJob = Cache::get('analytics:last_job_at');

        $this->table(['Metric', 'Value'], [
            ['active_items', $totals['active']],
            ['eligible_items', $totals['eligible']],
            ['ineligible_items', $totals['ineligible']],
            ['eligible_unclassified', $totals['eligible_unclassified']],
            ['eligible_timestamp_null', $totals['eligible_timestamp_null']],
            ['ineligible_timestamp_present', $totals['ineligible_timestamp_present']],
            ['analytics_queue_depth', $queueDepth ?? 'unavailable'],
            ['failed_analytics_jobs', $failed],
            ['last_sweep_at', $lastSweep ?? 'never'],
            ['last_analytics_job_at', $lastJob ?? 'never'],
        ]);

        $incomplete = $totals['eligible_unclassified'] > 0
            || $totals['eligible_timestamp_null'] > 0
            || $totals['ineligible_timestamp_present'] > 0
            || $queueDepth === null
            || $queueDepth > 0
            || $failed > 0
            || $lastSweep === null
            || ($totals['active'] > 0 && $lastJob === null);

        if ($this->option('fail-on-incomplete') && $incomplete) {
            $this->error('Analytics exit gate belum lengkap.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function queueDepth(): ?int
    {
        try {
            return (int) Queue::size('analytics');
        } catch (Throwable) {
            return null;
        }
    }
}
