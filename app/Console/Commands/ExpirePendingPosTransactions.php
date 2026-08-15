<?php

namespace App\Console\Commands;

use App\Actions\Pos\ExpirePendingPosTransactionAction;
use App\Enums\PosTransactionStatus;
use App\Models\PosTransaction;
use App\Models\Tenant;
use App\Services\TenantContext;
use App\Support\PosPendingExpiry;
use Illuminate\Console\Command;

class ExpirePendingPosTransactions extends Command
{
    protected $signature = 'pos:expire-pending {--batch=100 : Maximum transactions processed per run}';

    protected $description = 'Expire checkout POS pending yang telah melewati TTL';

    public function handle(ExpirePendingPosTransactionAction $action): int
    {
        $batch = max(1, min(1000, (int) $this->option('batch')));
        $candidates = PosTransaction::withoutGlobalScopes()
            ->where('status', PosTransactionStatus::PendingPayment->value)
            ->where('created_at', '<=', PosPendingExpiry::cutoff())
            ->orderBy('id')
            ->limit($batch)
            ->get(['id', 'tenant_id']);

        $expired = 0;
        foreach ($candidates->groupBy('tenant_id') as $tenantId => $transactions) {
            $tenant = Tenant::find($tenantId);
            if ($tenant === null) {
                continue;
            }

            TenantContext::run($tenant, function () use ($transactions, $action, &$expired): void {
                foreach ($transactions as $transaction) {
                    $expired += $action->execute($transaction->getKey()) ? 1 : 0;
                }
            });
        }

        $this->info("Expired {$expired} pending POS transaction(s).");

        return self::SUCCESS;
    }
}
