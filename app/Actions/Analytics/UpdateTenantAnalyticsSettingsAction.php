<?php

namespace App\Actions\Analytics;

use App\Actions\Audit\RecordAuditAction;
use App\Events\TenantAnalyticsRecalculationRequested;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTenantAnalyticsSettingsAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $deadStockDays, User $actor, ?AuditContext $context = null): Tenant
    {
        OwnerActorGuard::assert($actor);
        if ($deadStockDays < 0) {
            throw ValidationException::withMessages([
                'dead_stock_days' => ['Dead stock days tidak boleh negatif.'],
            ]);
        }

        return DB::transaction(function () use ($deadStockDays, $actor, $context): Tenant {
            $tenant = Tenant::whereKey(TenantContext::id())->lockForUpdate()->firstOrFail();
            $old = (int) $tenant->dead_stock_days;
            if ($old === $deadStockDays) {
                return $tenant;
            }

            $tenant->update(['dead_stock_days' => $deadStockDays]);
            $this->audit->execute(
                'tenant.analytics_settings_updated',
                $actor,
                $tenant,
                oldValues: ['dead_stock_days' => $old],
                newValues: ['dead_stock_days' => $deadStockDays],
                context: $context,
            );
            TenantAnalyticsRecalculationRequested::dispatch(
                (int) $tenant->getKey(),
                'dead_stock_days_changed',
            );

            return $tenant->fresh();
        });
    }
}
