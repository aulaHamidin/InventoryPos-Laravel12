<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Admin;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\SupportGuard;
use Illuminate\Support\Facades\DB;

final class ResetSupportMfaAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, Admin $support, ?AuditContext $context = null): Admin
    {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $support, $context): Admin {
            $support = SupportGuard::target(Admin::query()->lockForUpdate()->findOrFail($support->getKey()));
            $support->forceFill([
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_code_hashes' => null,
                'two_factor_last_used_step' => null,
                'auth_version' => $support->auth_version + 1,
            ])->save();
            $this->audit->execute('platform.support_mfa_reset', $actor, $support, newValues: ['auth_version' => $support->auth_version, 'mfa_enrolled' => false], context: $context, global: true);

            return $support;
        });
    }
}
