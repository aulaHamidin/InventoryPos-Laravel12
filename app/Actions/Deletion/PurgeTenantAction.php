<?php

namespace App\Actions\Deletion;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\TenantDeletionStatus;
use App\Models\Tenant;
use App\Models\TenantDeletionRequest;
use App\Support\BillingClock;
use App\Support\IdentityHasher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PurgeTenantAction
{
    public function __construct(
        private readonly BillingClock $clock,
        private readonly IdentityHasher $identityHasher,
        private readonly RecordAuditAction $audit,
    ) {}

    public function execute(TenantDeletionRequest $deletion, ?CarbonImmutable $asOf = null): void
    {
        $asOf ??= $this->clock->now();
        DB::transaction(function () use ($deletion, $asOf): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($deletion->tenant_id);
            $deletion = TenantDeletionRequest::query()->lockForUpdate()->findOrFail($deletion->getKey());
            if ($deletion->status !== TenantDeletionStatus::Queued || $deletion->purge_after?->isAfter(BillingClock::storage($asOf))) {
                throw new ConflictHttpException('Deletion is not queued and due.');
            }
            $requestId = $deletion->getKey();
            $identity = $this->identityHasher->value(sprintf('tenant:%d:%s', $tenant->getKey(), $tenant->slug));
            $tenant->delete();
            $this->audit->execute('tenant.purged', null, Tenant::class, newValues: [
                'request_id' => $requestId,
                'purged_at' => $asOf->toIso8601String(),
                'identity_hmac' => $identity,
            ], global: true);
        }, 3);
    }
}
