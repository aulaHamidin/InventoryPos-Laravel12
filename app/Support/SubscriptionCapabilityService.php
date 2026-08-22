<?php

namespace App\Support;

use App\Enums\OperationalStatus;
use App\Enums\SubscriptionCapability;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

final class SubscriptionCapabilityService
{
    /** @var array<int, Subscription|null> */
    private array $resolved = [];

    public function current(Tenant|int $tenant): ?Subscription
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        if (array_key_exists((int) $tenantId, $this->resolved)) {
            return $this->resolved[(int) $tenantId];
        }

        $subscription = Subscription::query()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('tenant_id', $tenantId)
            ->orderByRaw("subscriptions.status = 'expired' ASC")
            ->orderByDesc('subscriptions.id')
            ->select([
                'subscriptions.*',
                'plans.code as resolved_plan_code',
                'plans.name as resolved_plan_name',
                'plans.billing_interval as resolved_plan_interval',
                'plans.price as resolved_plan_price',
                'plans.is_trial as resolved_plan_is_trial',
                'plans.trial_days as resolved_plan_trial_days',
                'plans.is_active as resolved_plan_is_active',
                'plans.is_internal as resolved_plan_is_internal',
            ])
            ->first();
        if ($subscription !== null) {
            $plan = new Plan([
                'code' => $subscription->resolved_plan_code,
                'name' => $subscription->resolved_plan_name,
                'billing_interval' => $subscription->resolved_plan_interval,
                'price' => $subscription->resolved_plan_price,
                'is_trial' => $subscription->resolved_plan_is_trial,
                'trial_days' => $subscription->resolved_plan_trial_days,
                'is_active' => $subscription->resolved_plan_is_active,
                'is_internal' => $subscription->resolved_plan_is_internal,
            ]);
            $plan->forceFill(['id' => $subscription->plan_id]);
            $plan->exists = true;
            $subscription->setRelation('plan', $plan);
        }

        return $this->resolved[(int) $tenantId] = $subscription;
    }

    public function forget(Tenant|int $tenant): void
    {
        unset($this->resolved[(int) ($tenant instanceof Tenant ? $tenant->getKey() : $tenant)]);
    }

    public function allows(User $actor, SubscriptionCapability $capability): bool
    {
        $tenant = $actor->tenant;
        if (! $actor->is_active || $tenant === null) {
            return false;
        }

        if (in_array($capability, [SubscriptionCapability::Billing, SubscriptionCapability::Deletion], true)) {
            return $actor->role === UserRole::Owner;
        }

        if ($tenant->operational_status !== OperationalStatus::Active) {
            return false;
        }

        $subscription = $this->current($tenant);
        if ($subscription === null || $subscription->plan === null) {
            return false;
        }

        return match ($capability) {
            SubscriptionCapability::Read => true,
            SubscriptionCapability::Operate => $subscription->status->permitsOperations(),
            SubscriptionCapability::Configure => $subscription->status->permitsConfiguration(),
            SubscriptionCapability::Billing, SubscriptionCapability::Deletion => false,
        };
    }

    public function flags(User $actor): array
    {
        return [
            'read' => $this->allows($actor, SubscriptionCapability::Read),
            'operate' => $this->allows($actor, SubscriptionCapability::Operate),
            'configure' => $this->allows($actor, SubscriptionCapability::Configure),
            'billing' => $this->allows($actor, SubscriptionCapability::Billing),
            'deletion' => $this->allows($actor, SubscriptionCapability::Deletion),
        ];
    }
}
