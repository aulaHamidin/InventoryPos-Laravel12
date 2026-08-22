<?php

namespace App\Actions\Platform;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\OperationalStatus;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;

final class SetTenantOperationalStatusAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, Tenant $tenant, OperationalStatus $status, ?AuditContext $context = null): Tenant
    {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $tenant, $status, $context): Tenant {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $old = $tenant->operational_status;
            if ($old === $status) {
                return $tenant;
            }
            $tenant->forceFill(['operational_status' => $status])->save();
            if ($status === OperationalStatus::Banned) {
                User::query()->withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->increment('auth_version');
                User::query()->withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->each(fn (User $user) => $user->tokens()->delete());
            }
            $this->audit->execute($status === OperationalStatus::Banned ? 'platform.tenant_banned' : 'platform.tenant_unbanned', $actor, $tenant, oldValues: ['operational_status' => $old->value], newValues: ['operational_status' => $status->value], context: $context, tenantId: $tenant->getKey());

            return $tenant;
        }, 3);
    }
}
