<?php

namespace App\Console\Commands;

use App\Actions\Deletion\QueueDueTenantDeletionsAction;
use Illuminate\Console\Command;

final class QueueDueTenantDeletions extends Command
{
    protected $signature = 'tenant-deletion:queue-due';

    protected $description = 'Move approved and due tenant deletion requests to queued';

    public function handle(QueueDueTenantDeletionsAction $action): int
    {
        $this->info(sprintf('queued=%d', $action->execute()));

        return self::SUCCESS;
    }
}
