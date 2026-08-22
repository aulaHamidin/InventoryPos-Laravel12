<?php

namespace App\Actions\Deletion;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\TenantDeletionStatus;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\TenantDeletionRequest;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RejectTenantDeletionAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, TenantDeletionRequest $deletion, string $reason, ?AuditContext $context = null): TenantDeletionRequest
    {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $deletion, $reason, $context): TenantDeletionRequest {
            Tenant::query()->lockForUpdate()->findOrFail($deletion->tenant_id);
            $deletion = TenantDeletionRequest::query()->lockForUpdate()->findOrFail($deletion->getKey());
            if ($deletion->status !== TenantDeletionStatus::Requested) {
                throw new ConflictHttpException('Only requested deletion may be rejected.');
            }
            $deletion->forceFill([
                'status' => TenantDeletionStatus::Rejected,
                'reviewed_by_admin_id' => $actor->getKey(),
                'review_reason' => trim($reason),
                'reviewed_at' => now(),
            ])->save();
            $this->audit->execute('tenant.deletion_rejected', $actor, $deletion, oldValues: ['status' => 'requested'], newValues: ['status' => 'rejected', 'review_reason' => trim($reason)], context: $context, tenantId: $deletion->tenant_id);

            return $deletion;
        }, 3);
    }
}
