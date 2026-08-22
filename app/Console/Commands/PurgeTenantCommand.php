<?php

namespace App\Console\Commands;

use App\Actions\Deletion\PurgeTenantAction;
use App\Enums\TenantDeletionStatus;
use App\Models\TenantDeletionRequest;
use App\Support\BillingClock;
use Illuminate\Console\Command;

final class PurgeTenantCommand extends Command
{
    protected $signature = 'tenant-deletion:purge {--request= : Process one queued request ID}';

    protected $description = 'Purge only queued tenant deletions whose retention deadline has passed';

    public function handle(PurgeTenantAction $action, BillingClock $clock): int
    {
        $query = TenantDeletionRequest::query()
            ->where('status', TenantDeletionStatus::Queued)
            ->where('purge_after', '<=', BillingClock::storage($clock->now()))
            ->orderBy('id');
        if ($this->option('request')) {
            $query->whereKey((int) $this->option('request'));
        }
        $ids = $query->pluck('id');
        foreach ($ids as $id) {
            $action->execute(TenantDeletionRequest::query()->findOrFail($id));
        }
        $this->info(sprintf('purged=%d', $ids->count()));

        return self::SUCCESS;
    }
}
