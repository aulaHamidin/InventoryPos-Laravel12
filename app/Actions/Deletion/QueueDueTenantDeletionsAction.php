<?php

namespace App\Actions\Deletion;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\TenantDeletionStatus;
use App\Models\Tenant;
use App\Models\TenantDeletionRequest;
use App\Support\BillingClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class QueueDueTenantDeletionsAction
{
    public function __construct(private readonly BillingClock $clock, private readonly RecordAuditAction $audit) {}

    public function execute(?CarbonImmutable $asOf = null): int
    {
        $asOf ??= $this->clock->now();
        $count = 0;
        TenantDeletionRequest::query()->where('status', TenantDeletionStatus::Approved)->where('purge_after', '<=', BillingClock::storage($asOf))
            ->orderBy('id')->eachById(function (TenantDeletionRequest $candidate) use (&$count, $asOf): void {
                DB::transaction(function () use ($candidate, &$count, $asOf): void {
                    Tenant::query()->lockForUpdate()->findOrFail($candidate->tenant_id);
                    $deletion = TenantDeletionRequest::query()->lockForUpdate()->findOrFail($candidate->getKey());
                    if ($deletion->status !== TenantDeletionStatus::Approved || $deletion->purge_after?->isAfter(BillingClock::storage($asOf))) {
                        return;
                    }
                    $deletion->forceFill(['status' => TenantDeletionStatus::Queued, 'queued_at' => BillingClock::storage($asOf)])->save();
                    $this->audit->execute('tenant.deletion_queued', null, $deletion, oldValues: ['status' => 'approved'], newValues: ['status' => 'queued'], tenantId: $deletion->tenant_id);
                    $count++;
                }, 3);
            });

        return $count;
    }
}
