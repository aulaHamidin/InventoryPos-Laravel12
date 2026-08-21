<?php

namespace App\Listeners;

use App\Events\ItemAnalyticsRecalculationRequested;
use App\Jobs\RecalculateItemAnalyticsJob;

class QueueItemAnalyticsRecalculation
{
    public function handle(ItemAnalyticsRecalculationRequested $event): void
    {
        $itemIds = array_values(array_unique(array_map('intval', $event->itemIds)));
        sort($itemIds, SORT_NUMERIC);

        foreach ($itemIds as $itemId) {
            RecalculateItemAnalyticsJob::dispatch($event->tenantId, $itemId, $event->reason);
        }
    }
}
