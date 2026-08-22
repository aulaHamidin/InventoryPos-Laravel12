<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Admin;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\SupportGuard;
use Illuminate\Support\Facades\DB;

final class DeactivateSupportAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, Admin $support, ?AuditContext $context = null): Admin
    {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $support, $context): Admin {
            $support = SupportGuard::target(Admin::query()->lockForUpdate()->findOrFail($support->getKey()));
            if (! $support->is_active) {
                return $support;
            }
            $support->forceFill(['is_active' => false, 'auth_version' => $support->auth_version + 1])->save();
            $this->audit->execute('platform.support_deactivated', $actor, $support, oldValues: ['is_active' => true], newValues: ['is_active' => false, 'auth_version' => $support->auth_version], context: $context, global: true);

            return $support;
        });
    }
}
