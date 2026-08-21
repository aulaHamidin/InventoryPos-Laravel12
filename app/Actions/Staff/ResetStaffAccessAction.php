<?php

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditAction;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\StaffGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ResetStaffAccessAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $staffId, array $data, User $actor, ?AuditContext $context = null): User
    {
        StaffGuard::assertOwner($actor);
        StaffGuard::target($staffId);
        $validated = Validator::make($data, [
            'password' => ['required', 'confirmed', Password::min(12)],
        ])->validate();

        return DB::transaction(function () use ($staffId, $validated, $actor, $context): User {
            $staff = User::whereKey($staffId)->lockForUpdate()->firstOrFail();
            $oldVersion = (int) $staff->auth_version;
            $staff->forceFill([
                'password' => $validated['password'],
                'auth_version' => $oldVersion + 1,
            ])->save();
            $staff->tokens()->delete();
            $this->audit->execute('staff.access_reset', $actor, $staff, oldValues: [
                'auth_version' => $oldVersion,
            ], newValues: [
                'auth_version' => $oldVersion + 1,
            ], context: $context);

            return $staff->fresh();
        });
    }
}
