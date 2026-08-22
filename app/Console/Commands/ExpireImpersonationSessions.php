<?php

namespace App\Console\Commands;

use App\Actions\Impersonation\ExpireImpersonationSessionsAction;
use Illuminate\Console\Command;

final class ExpireImpersonationSessions extends Command
{
    protected $signature = 'impersonation:expire';

    protected $description = 'Expire platform impersonation sessions whose 30-minute window has elapsed';

    public function handle(ExpireImpersonationSessionsAction $action): int
    {
        $this->info(sprintf('Expired impersonation sessions: %d', $action->execute()));

        return self::SUCCESS;
    }
}
