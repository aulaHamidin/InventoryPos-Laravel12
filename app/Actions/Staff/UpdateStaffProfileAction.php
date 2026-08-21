<?php

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditAction;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\StaffGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateStaffProfileAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $staffId, array $data, User $actor, ?AuditContext $context = null): User
    {
        StaffGuard::assertOwner($actor);
        $staff = StaffGuard::target($staffId);
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->getKey())],
            'no_hp' => ['required', 'string', 'max:20', Rule::unique('users', 'no_hp')->ignore($staff->getKey())],
        ])->validate();

        try {
            return DB::transaction(function () use ($staffId, $validated, $actor, $context): User {
                $locked = User::whereKey($staffId)->lockForUpdate()->firstOrFail();
                $old = $locked->only(['name', 'email', 'no_hp']);
                $locked->update([
                    'name' => trim($validated['name']),
                    'email' => mb_strtolower(trim($validated['email'])),
                    'no_hp' => trim($validated['no_hp']),
                ]);
                $new = $locked->fresh()->only(['name', 'email', 'no_hp']);
                if ($old !== $new) {
                    $this->audit->execute('staff.profile_updated', $actor, $locked, oldValues: $old, newValues: $new, context: $context);
                }

                return $locked->fresh();
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '23000') {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'staff' => ['Email atau nomor HP sudah digunakan.'],
            ]);
        }
    }
}
