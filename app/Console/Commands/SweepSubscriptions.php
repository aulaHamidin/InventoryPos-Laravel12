<?php

namespace App\Console\Commands;

use App\Actions\Billing\SweepSubscriptionsAction;
use Illuminate\Console\Command;

final class SweepSubscriptions extends Command
{
    protected $signature = 'billing:sweep-subscriptions';

    protected $description = 'Apply due subscription status transitions using Jakarta business time';

    public function handle(SweepSubscriptionsAction $action): int
    {
        $counts = $action->execute();
        $this->info(sprintf('expired=%d past_due=%d suspended=%d', $counts['expired'], $counts['past_due'], $counts['suspended']));

        return self::SUCCESS;
    }
}
