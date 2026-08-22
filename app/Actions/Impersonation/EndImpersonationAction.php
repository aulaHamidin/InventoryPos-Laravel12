<?php

namespace App\Actions\Impersonation;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\ImpersonationEndReason;
use App\Models\Admin;
use App\Models\ImpersonationSession;
use App\Support\AuditContext;
use App\Support\BillingClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class EndImpersonationAction
{
    public function __construct(private readonly BillingClock $clock, private readonly RecordAuditAction $audit) {}

    public function execute(ImpersonationSession $impersonation, ImpersonationEndReason $reason, ?Admin $actor = null, ?CarbonImmutable $asOf = null, ?AuditContext $context = null): ImpersonationSession
    {
        $asOf ??= $this->clock->now();

        return DB::transaction(function () use ($impersonation, $reason, $actor, $asOf, $context): ImpersonationSession {
            $impersonation = ImpersonationSession::query()->lockForUpdate()->findOrFail($impersonation->getKey());
            if ($impersonation->ended_at !== null) {
                return $impersonation;
            }
            $impersonation->forceFill(['ended_at' => BillingClock::storage($asOf), 'end_reason' => $reason])->save();
            $this->audit->execute(
                $reason === ImpersonationEndReason::Expired ? 'platform.impersonation_expired' : 'platform.impersonation_ended',
                $actor,
                $impersonation,
                newValues: ['end_reason' => $reason->value, 'ended_at' => $asOf->toIso8601String()],
                context: $context,
                tenantId: $impersonation->tenant_id,
            );
            if (request()->hasSession() && session()->get(StartImpersonationAction::SESSION_KEY) === $impersonation->getKey()) {
                Auth::guard('web')->logout();
                session()->forget([StartImpersonationAction::SESSION_KEY, 'tenant_auth_version']);
            }

            return $impersonation;
        }, 3);
    }
}
