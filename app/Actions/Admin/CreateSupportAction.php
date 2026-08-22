<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\AdminRole;
use App\Models\Admin;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\CredentialContract;
use Illuminate\Support\Facades\DB;

final class CreateSupportAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, string $name, string $email, string $password, ?AuditContext $context = null): Admin
    {
        AdminActorGuard::superAdmin($actor);
        CredentialContract::password($password);

        return DB::transaction(function () use ($actor, $name, $email, $password, $context): Admin {
            $support = new Admin(['name' => trim($name), 'email' => strtolower(trim($email)), 'password' => $password]);
            $support->forceFill(['role' => AdminRole::Support, 'is_active' => true, 'auth_version' => 1]);
            $support->save();
            $this->audit->execute('platform.support_created', $actor, $support, newValues: [
                'name' => $support->name, 'email' => $support->email, 'role' => $support->role->value, 'is_active' => true,
            ], context: $context, global: true);

            return $support;
        });
    }
}
