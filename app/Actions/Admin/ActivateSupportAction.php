<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Admin;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\SupportGuard;
use Illuminate\Support\Facades\DB;

final class ActivateSupportAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, Admin $support, ?AuditContext $context = null): Admin
    {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $support, $context): Admin {
            $support = SupportGuard::target(Admin::query()->lockForUpdate()->findOrFail($support->getKey()));
            if ($support->is_active) {
                return $support;
            }
            $support->forceFill(['is_active' => true])->save();
            $this->audit->execute('platform.support_activated', $actor, $support, oldValues: ['is_active' => false], newValues: ['is_active' => true, 'auth_version' => $support->auth_version], context: $context, global: true);

            return $support;
        });
    }
}
