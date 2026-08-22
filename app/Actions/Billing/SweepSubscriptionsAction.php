<?php

namespace App\Actions\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Support\BillingClock;
use Carbon\CarbonImmutable;

final class SweepSubscriptionsAction
{
    public function __construct(private readonly BillingClock $clock, private readonly TransitionSubscriptionAction $transition) {}

    /** @return array{expired: int, past_due: int, suspended: int} */
    public function execute(?CarbonImmutable $asOf = null): array
    {
        $asOf ??= $this->clock->now();
        $counts = ['expired' => 0, 'past_due' => 0, 'suspended' => 0];

        Subscription::query()->where('status', SubscriptionStatus::Trial)->where('ends_at', '<=', BillingClock::storage($asOf))
            ->orderBy('id')->eachById(function (Subscription $subscription) use (&$counts): void {
                $subscription = $this->transition->execute($subscription, SubscriptionStatus::Expired, expectedFrom: SubscriptionStatus::Trial);
                if ($subscription->wasChanged('status')) {
                    $counts['expired']++;
                }
            });
        Subscription::query()->where('status', SubscriptionStatus::Active)->where('ends_at', '<=', BillingClock::storage($asOf))
            ->orderBy('id')->eachById(function (Subscription $subscription) use (&$counts): void {
                if ($subscription->plan?->is_internal) {
                    return;
                }
                $subscription = $this->transition->execute($subscription, SubscriptionStatus::PastDue, expectedFrom: SubscriptionStatus::Active);
                if ($subscription->wasChanged('status')) {
                    $counts['past_due']++;
                }
            });
        Subscription::query()->where('status', SubscriptionStatus::PastDue)->where('ends_at', '<=', BillingClock::storage($asOf->subDays(7)))
            ->orderBy('id')->eachById(function (Subscription $subscription) use (&$counts): void {
                $subscription = $this->transition->execute($subscription, SubscriptionStatus::Suspended, expectedFrom: SubscriptionStatus::PastDue);
                if ($subscription->wasChanged('status')) {
                    $counts['suspended']++;
                }
            });

        return $counts;
    }
}
