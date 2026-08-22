<?php

namespace App\Actions\Impersonation;

use App\Enums\ImpersonationEndReason;
use App\Models\ImpersonationSession;
use App\Support\BillingClock;
use Carbon\CarbonImmutable;

final class ExpireImpersonationSessionsAction
{
    public function __construct(private readonly BillingClock $clock, private readonly EndImpersonationAction $end) {}

    public function execute(?CarbonImmutable $asOf = null): int
    {
        $asOf ??= $this->clock->now();
        $expired = 0;

        ImpersonationSession::query()
            ->whereNull('ended_at')
            ->where('expires_at', '<=', BillingClock::storage($asOf))
            ->orderBy('id')
            ->eachById(function (ImpersonationSession $session) use (&$expired, $asOf): void {
                $this->end->execute($session, ImpersonationEndReason::Expired, asOf: $asOf);
                $expired++;
            });

        return $expired;
    }
}
