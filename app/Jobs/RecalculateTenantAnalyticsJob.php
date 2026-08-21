<?php

namespace App\Jobs;

use App\Models\Item;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateTenantAnalyticsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $reason = 'tenant',
    ) {
        $this->onQueue('analytics');
    }

    public function uniqueId(): string
    {
        return 'tenant:'.$this->tenantId;
    }

    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        TenantContext::run($tenant, function (): void {
            Item::where('is_active', true)->orderBy('id')->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    RecalculateItemAnalyticsJob::dispatch(
                        $this->tenantId,
                        (int) $item->getKey(),
                        $this->reason,
                    );
                }
            });
        });
    }
}
