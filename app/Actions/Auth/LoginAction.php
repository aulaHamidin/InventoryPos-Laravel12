<?php

namespace App\Actions\Auth;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(string $email, string $password, string $deviceName, ?AuditContext $context = null): array
    {
        $user = User::withoutGlobalScope(TenantScope::class)->with('tenant')->where('email', $email)->first();

        if ($user === null || ! $user->is_active || ! Hash::check($password, $user->password) || $user->tenant === null || ! $user->tenant->canOperate()) {
            throw ValidationException::withMessages(['email' => ['Kredensial tidak valid.']]);
        }

        TenantContext::set($user->tenant);
        $token = $user->createToken($deviceName)->plainTextToken;
        $this->audit->execute('auth.login', $user, $user, context: $context, metadata: ['device_name' => $deviceName]);

        return ['user' => $user, 'token' => $token];
    }
}
