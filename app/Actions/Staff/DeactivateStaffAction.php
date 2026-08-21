<?php

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditAction;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\StaffGuard;
use Illuminate\Support\Facades\DB;

class DeactivateStaffAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $staffId, User $actor, ?AuditContext $context = null): User
    {
        StaffGuard::assertOwner($actor);
        StaffGuard::target($staffId);

        return DB::transaction(function () use ($staffId, $actor, $context): User {
            $staff = User::whereKey($staffId)->lockForUpdate()->firstOrFail();
            if (! $staff->is_active) {
                return $staff;
            }

            $oldVersion = (int) $staff->auth_version;
            $staff->forceFill([
                'is_active' => false,
                'auth_version' => $oldVersion + 1,
            ])->save();
            $staff->tokens()->delete();
            $this->audit->execute('staff.deactivated', $actor, $staff, oldValues: [
                'is_active' => true,
                'auth_version' => $oldVersion,
            ], newValues: [
                'is_active' => false,
                'auth_version' => $oldVersion + 1,
            ], context: $context);

            return $staff->fresh();
        });
    }
}
