<?php

namespace App\Listeners;

use App\Events\TenantAnalyticsRecalculationRequested;
use App\Jobs\RecalculateTenantAnalyticsJob;

class QueueTenantAnalyticsRecalculation
{
    public function handle(TenantAnalyticsRecalculationRequested $event): void
    {
        RecalculateTenantAnalyticsJob::dispatch($event->tenantId, $event->reason);
    }
}
