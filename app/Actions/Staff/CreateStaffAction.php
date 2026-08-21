<?php

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\StaffGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CreateStaffAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(array $data, User $actor, ?AuditContext $context = null): User
    {
        StaffGuard::assertOwner($actor);
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'no_hp' => ['required', 'string', 'max:20', 'unique:users,no_hp'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ])->validate();

        try {
            return DB::transaction(function () use ($validated, $actor, $context): User {
                $staff = new User([
                    'name' => trim($validated['name']),
                    'email' => mb_strtolower(trim($validated['email'])),
                    'no_hp' => trim($validated['no_hp']),
                    'password' => $validated['password'],
                ]);
                $staff->forceFill([
                    'role' => UserRole::Staff,
                    'is_active' => true,
                    'auth_version' => 1,
                ])->save();

                $this->audit->execute('staff.created', $actor, $staff, newValues: [
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'no_hp' => $staff->no_hp,
                    'is_active' => true,
                ], context: $context);

                return $staff;
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
