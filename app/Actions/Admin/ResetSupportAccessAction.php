<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Admin;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\CredentialContract;
use App\Support\SupportGuard;
use Illuminate\Support\Facades\DB;

final class ResetSupportAccessAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, Admin $support, string $newPassword, ?AuditContext $context = null): Admin
    {
        AdminActorGuard::superAdmin($actor);
        CredentialContract::password($newPassword);

        return DB::transaction(function () use ($actor, $support, $newPassword, $context): Admin {
            $support = SupportGuard::target(Admin::query()->lockForUpdate()->findOrFail($support->getKey()));
            $support->forceFill(['password' => $newPassword, 'auth_version' => $support->auth_version + 1])->save();
            $this->audit->execute('platform.support_access_reset', $actor, $support, newValues: ['auth_version' => $support->auth_version], context: $context, global: true);

            return $support;
        });
    }
}
