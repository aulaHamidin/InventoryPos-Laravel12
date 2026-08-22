<?php

namespace App\Support;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;

final class MrrCalculator
{
    /** @return array{mrr: string, past_due: string} */
    public function totals(): array
    {
        $rows = Subscription::query()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('plans.is_internal', false)
            ->where('plans.is_trial', false)
            ->whereIn('subscriptions.status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->get(['subscriptions.status', 'plans.billing_interval', 'plans.price']);

        $mrr = '0.00';
        $pastDue = '0.00';
        foreach ($rows as $row) {
            $monthly = $row->billing_interval === BillingInterval::Yearly->value
                ? Decimal::div((string) $row->price, 12)
                : Decimal::money((string) $row->price);
            if ($row->status === SubscriptionStatus::Active) {
                $mrr = Decimal::add($mrr, $monthly);
            } else {
                $pastDue = Decimal::add($pastDue, $monthly);
            }
        }

        return ['mrr' => $mrr, 'past_due' => $pastDue];
    }
}
