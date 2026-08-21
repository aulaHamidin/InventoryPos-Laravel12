<?php

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditAction;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\StaffGuard;
use Illuminate\Support\Facades\DB;

class ActivateStaffAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $staffId, User $actor, ?AuditContext $context = null): User
    {
        StaffGuard::assertOwner($actor);
        StaffGuard::target($staffId);

        return DB::transaction(function () use ($staffId, $actor, $context): User {
            $staff = User::whereKey($staffId)->lockForUpdate()->firstOrFail();
            if ($staff->is_active) {
                return $staff;
            }

            $staff->forceFill(['is_active' => true])->save();
            $this->audit->execute('staff.activated', $actor, $staff, oldValues: [
                'is_active' => false,
            ], newValues: [
                'is_active' => true,
            ], context: $context);

            return $staff->fresh();
        });
    }
}
