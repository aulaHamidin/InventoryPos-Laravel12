<?php

namespace App\Actions\Auth;

use App\Actions\Audit\RecordAuditAction;
use App\Models\User;
use App\Support\AuditContext;

class LogoutAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(User $user, ?AuditContext $context = null): void
    {
        $this->audit->execute('auth.logout', $user, $user, context: $context);
        $user->currentAccessToken()?->delete();
    }
}
