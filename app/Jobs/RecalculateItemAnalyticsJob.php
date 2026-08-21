<?php

namespace App\Jobs;

use App\Actions\Analytics\RecalculateItemAnalyticsAction;
use App\Models\Tenant;
use App\Services\TenantContext;
use App\Support\AnalyticsClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RecalculateItemAnalyticsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $itemId,
        public readonly string $reason = 'event',
    ) {
        $this->onQueue('analytics');
    }

    public function uniqueId(): string
    {
        return $this->tenantId.':'.$this->itemId;
    }

    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(RecalculateItemAnalyticsAction $action, AnalyticsClock $clock): void
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        TenantContext::run($tenant, fn () => $action->execute($this->itemId, reason: $this->reason));
        Cache::put('analytics:last_job_at', $clock->now()->toIso8601String(), $clock->now()->addDays(2));
    }

    public function failed(Throwable $exception): void
    {
        Cache::put('analytics:last_failure', [
            'tenant_id' => $this->tenantId,
            'item_id' => $this->itemId,
            'message' => $exception->getMessage(),
        ], CarbonImmutable::now(AnalyticsClock::BUSINESS_TIMEZONE)->addDays(7));
    }
}
