<?php

namespace App\Actions\Deletion;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\TenantDeletionStatus;
use App\Models\Tenant;
use App\Models\TenantDeletionRequest;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\DeletionActorGuard;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RequestTenantDeletionAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(User $actor, string $reason, ?AuditContext $context = null): TenantDeletionRequest
    {
        $actor = DeletionActorGuard::owner($actor);
        $reason = trim($reason);
        abort_unless(mb_strlen($reason) >= 10 && mb_strlen($reason) <= 1000, 422, 'Deletion reason must contain 10 to 1000 characters.');

        return DB::transaction(function () use ($actor, $reason, $context): TenantDeletionRequest {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($actor->tenant_id);
            if (TenantDeletionRequest::query()->where('tenant_id', $tenant->getKey())->whereIn('status', [
                TenantDeletionStatus::Requested, TenantDeletionStatus::Approved, TenantDeletionStatus::Queued,
            ])->exists()) {
                throw new ConflictHttpException('DELETION_REQUEST_EXISTS');
            }
            $request = TenantDeletionRequest::query()->create([
                'tenant_id' => $tenant->getKey(),
                'requested_by_user_id' => $actor->getKey(),
                'status' => TenantDeletionStatus::Requested,
                'reason' => $reason,
            ]);
            $this->audit->execute('tenant.deletion_requested', $actor, $request, newValues: ['status' => 'requested', 'reason' => $reason], context: $context, tenantId: $tenant->getKey());

            return $request;
        }, 3);
    }
}
