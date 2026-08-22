<?php

namespace App\Actions\Impersonation;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Http\Middleware\EnsureTenantUserActive;
use App\Models\Admin;
use App\Models\ImpersonationSession;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\BillingClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class StartImpersonationAction
{
    public const SESSION_KEY = 'platform_impersonation_id';

    public function __construct(private readonly BillingClock $clock, private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, User $targetOwner, string $reason, string $browserSessionId, ?CarbonImmutable $asOf = null, ?AuditContext $context = null): ImpersonationSession
    {
        AdminActorGuard::active($actor);
        $reason = trim($reason);
        abort_unless(mb_strlen($reason) >= 10 && mb_strlen($reason) <= 1000, 422, 'Impersonation reason must contain 10 to 1000 characters.');
        abort_if($browserSessionId === '', 422, 'A browser session is required.');
        $asOf ??= $this->clock->now();

        return DB::transaction(function () use ($actor, $targetOwner, $reason, $browserSessionId, $asOf, $context): ImpersonationSession {
            $target = User::query()->withoutGlobalScopes()->findOrFail($targetOwner->getKey());
            $tenant = Tenant::query()->findOrFail($target->tenant_id);
            abort_unless($target->role === UserRole::Owner && $target->is_active && $tenant->operational_status === OperationalStatus::Active, 403);
            $impersonation = ImpersonationSession::query()->create([
                'admin_id' => $actor->getKey(),
                'tenant_id' => $tenant->getKey(),
                'target_user_id' => $target->getKey(),
                'reason' => $reason,
                'session_fingerprint_hash' => self::fingerprint($browserSessionId, $actor->getKey()),
                'started_at' => BillingClock::storage($asOf),
                'expires_at' => BillingClock::storage($asOf->addMinutes(30)),
            ]);
            Auth::guard('web')->login($target);
            session()->put(EnsureTenantUserActive::SESSION_KEY, (int) $target->auth_version);
            session()->put(self::SESSION_KEY, $impersonation->getKey());
            $this->audit->execute('platform.impersonation_started', $actor, $impersonation, newValues: [
                'target_user_id' => $target->getKey(), 'expires_at' => $asOf->addMinutes(30)->toIso8601String(), 'reason' => $reason,
            ], context: $context, tenantId: $tenant->getKey());

            return $impersonation;
        }, 3);
    }

    public static function fingerprint(string $sessionId, int $adminId): string
    {
        return hash_hmac('sha256', $adminId.'|'.$sessionId, (string) config('app.key'));
    }
}
