<?php

namespace App\Actions\Platform;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\User;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\CredentialContract;
use Illuminate\Support\Facades\DB;

final class ResetOwnerAccessAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, User $owner, string $newPassword, ?AuditContext $context = null): User
    {
        AdminActorGuard::superAdmin($actor);
        CredentialContract::password($newPassword);

        return DB::transaction(function () use ($actor, $owner, $newPassword, $context): User {
            $owner = User::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($owner->getKey());
            abort_unless($owner->role === UserRole::Owner, 403);
            $owner->forceFill(['password' => $newPassword, 'auth_version' => $owner->auth_version + 1])->save();
            $owner->tokens()->delete();
            $this->audit->execute('platform.owner_access_reset', $actor, $owner, newValues: ['auth_version' => $owner->auth_version], context: $context, tenantId: $owner->tenant_id);

            return $owner;
        });
    }
}
