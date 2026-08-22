<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\SubscriptionStatus;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrialClaim;
use App\Models\User;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\BillingClock;
use App\Support\BillingPeriodCalculator;
use App\Support\IdentityHasher;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CreateSubscriptionAction
{
    public function __construct(
        private readonly BillingClock $clock,
        private readonly BillingPeriodCalculator $periods,
        private readonly IdentityHasher $identityHasher,
        private readonly RecordSubscriptionEventAction $events,
        private readonly RecordAuditAction $audit,
    ) {}

    public function execute(
        Admin $actor,
        Tenant $tenant,
        Plan $plan,
        bool $trial,
        ?string $ownerPhone = null,
        ?CarbonImmutable $asOf = null,
        ?AuditContext $context = null,
    ): Subscription {
        AdminActorGuard::superAdmin($actor);
        $asOf ??= $this->clock->now();

        try {
            return DB::transaction(function () use ($actor, $tenant, $plan, $trial, $ownerPhone, $asOf, $context): Subscription {
                $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
                $plan = Plan::query()->findOrFail($plan->getKey());
                if (! $plan->is_active || $plan->is_internal || ($trial && ! $plan->is_trial)) {
                    abort(422, 'Plan is not eligible.');
                }
                if (Subscription::query()->where('tenant_id', $tenant->getKey())->whereNot('status', SubscriptionStatus::Expired->value)->exists()) {
                    throw new ConflictHttpException('Tenant already has a current subscription.');
                }

                if ($trial) {
                    $phone = $ownerPhone ?? User::query()->withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->where('role', 'owner')->value('no_hp');
                    if (! is_string($phone) || $phone === '') {
                        abort(422, 'Owner phone is required for trial eligibility.');
                    }
                    TrialClaim::query()->create(['phone_hash' => $this->identityHasher->phone($phone)]);
                }

                $start = $asOf;
                $end = $trial
                    ? $asOf->addDays((int) $plan->trial_days)
                    : $this->periods->end($asOf, $plan->billing_interval);
                $subscription = Subscription::query()->create([
                    'tenant_id' => $tenant->getKey(),
                    'plan_id' => $plan->getKey(),
                    'status' => $trial ? SubscriptionStatus::Trial : SubscriptionStatus::Suspended,
                    'starts_at' => BillingClock::storage($start),
                    'ends_at' => BillingClock::storage($end),
                ]);
                $this->events->execute($subscription, 'created', null, $subscription->status, $actor);
                $this->audit->execute('billing.subscription_created', $actor, $subscription, newValues: [
                    'plan_id' => $plan->getKey(), 'status' => $subscription->status->value,
                ], context: $context, tenantId: $tenant->getKey());

                return $subscription;
            }, 3);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'trial_claims_phone_hash_unique')) {
                throw new ConflictHttpException('Lifetime trial has already been claimed.', previous: $exception);
            }
            throw $exception;
        }
    }
}
