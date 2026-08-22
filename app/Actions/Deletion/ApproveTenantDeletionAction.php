<?php

namespace App\Actions\Deletion;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\OperationalStatus;
use App\Enums\TenantDeletionStatus;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\TenantDeletionRequest;
use App\Models\User;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\BillingClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ApproveTenantDeletionAction
{
    public function __construct(private readonly BillingClock $clock, private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, TenantDeletionRequest $deletion, ?CarbonImmutable $asOf = null, ?AuditContext $context = null): TenantDeletionRequest
    {
        AdminActorGuard::superAdmin($actor);
        $asOf ??= $this->clock->now();

        return DB::transaction(function () use ($actor, $deletion, $asOf, $context): TenantDeletionRequest {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($deletion->tenant_id);
            $deletion = TenantDeletionRequest::query()->lockForUpdate()->findOrFail($deletion->getKey());
            if ($deletion->status !== TenantDeletionStatus::Requested) {
                throw new ConflictHttpException('Only requested deletion may be approved.');
            }
            $deletion->forceFill([
                'status' => TenantDeletionStatus::Approved,
                'reviewed_by_admin_id' => $actor->getKey(),
                'previous_operational_status' => $tenant->operational_status,
                'reviewed_at' => BillingClock::storage($asOf),
                'purge_after' => BillingClock::storage($asOf->addDays(30)),
            ])->save();
            $tenant->forceFill(['operational_status' => OperationalStatus::Banned])->save();
            User::query()->withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->increment('auth_version');
            User::query()->withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->each(fn (User $user) => $user->tokens()->delete());
            $this->audit->execute('tenant.deletion_approved', $actor, $deletion, oldValues: ['status' => 'requested'], newValues: [
                'status' => 'approved', 'purge_after' => $asOf->addDays(30)->toIso8601String(),
            ], context: $context, tenantId: $tenant->getKey());

            return $deletion;
        }, 3);
    }
}
