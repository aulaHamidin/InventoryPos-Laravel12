<?php

namespace App\Actions\Deletion;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\AdminRole;
use App\Enums\TenantDeletionStatus;
use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\TenantDeletionRequest;
use App\Models\User;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\DeletionActorGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CancelTenantDeletionAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(User|Admin $actor, TenantDeletionRequest $deletion, ?AuditContext $context = null): TenantDeletionRequest
    {
        $actor = $actor instanceof Admin
            ? tap($actor, static fn (Admin $admin) => AdminActorGuard::superAdmin($admin))
            : DeletionActorGuard::owner($actor);

        return DB::transaction(function () use ($actor, $deletion, $context): TenantDeletionRequest {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($deletion->tenant_id);
            $deletion = TenantDeletionRequest::query()->lockForUpdate()->findOrFail($deletion->getKey());
            $allowed = ($actor instanceof User && $actor->role === UserRole::Owner && (int) $actor->tenant_id === (int) $tenant->getKey() && $deletion->status === TenantDeletionStatus::Requested)
                || ($actor instanceof Admin && $actor->role === AdminRole::SuperAdmin && in_array($deletion->status, [TenantDeletionStatus::Requested, TenantDeletionStatus::Approved], true));
            if (! $allowed) {
                throw new AuthorizationException;
            }
            if ($deletion->status === TenantDeletionStatus::Queued) {
                throw new ConflictHttpException('Queued deletion cannot be cancelled.');
            }
            $old = $deletion->status;
            if ($old === TenantDeletionStatus::Approved && $deletion->previous_operational_status !== null) {
                $tenant->forceFill(['operational_status' => $deletion->previous_operational_status])->save();
            }
            $deletion->forceFill(['status' => TenantDeletionStatus::Cancelled, 'cancelled_at' => now()])->save();
            $this->audit->execute('tenant.deletion_cancelled', $actor, $deletion, oldValues: ['status' => $old->value], newValues: ['status' => 'cancelled'], context: $context, tenantId: $tenant->getKey());

            return $deletion;
        }, 3);
    }
}
