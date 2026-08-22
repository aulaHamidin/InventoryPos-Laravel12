<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Admin;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\SupportGuard;
use Illuminate\Support\Facades\DB;

final class UpdateSupportProfileAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, Admin $support, string $name, string $email, ?AuditContext $context = null): Admin
    {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $support, $name, $email, $context): Admin {
            $support = SupportGuard::target(Admin::query()->lockForUpdate()->findOrFail($support->getKey()));
            $old = $support->only(['name', 'email']);
            $support->fill(['name' => trim($name), 'email' => strtolower(trim($email))])->save();
            if ($old !== $support->only(['name', 'email'])) {
                $this->audit->execute('platform.support_profile_updated', $actor, $support, oldValues: $old, newValues: $support->only(['name', 'email']), context: $context, global: true);
            }

            return $support;
        });
    }
}
