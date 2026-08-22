<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\BillingPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Admin;
use App\Models\BillingPayment;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\BillingClock;
use App\Support\BillingPeriodCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class VerifyManualPaymentAction
{
    public function __construct(
        private readonly BillingClock $clock,
        private readonly BillingPeriodCalculator $periods,
        private readonly RecordSubscriptionEventAction $events,
        private readonly RecordAuditAction $audit,
    ) {}

    public function execute(Admin $actor, BillingPayment $payment, ?CarbonImmutable $paidAt = null, ?AuditContext $context = null): BillingPayment
    {
        AdminActorGuard::superAdmin($actor);
        $paidAt ??= $this->clock->now();

        return DB::transaction(function () use ($actor, $payment, $paidAt, $context): BillingPayment {
            Tenant::query()->lockForUpdate()->findOrFail($payment->tenant_id);
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($payment->subscription_id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            $payment = BillingPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($payment->status === BillingPaymentStatus::Paid) {
                return $payment;
            }
            if ($payment->status !== BillingPaymentStatus::Pending || $invoice->status !== InvoiceStatus::Open) {
                throw new ConflictHttpException('Payment cannot be verified in its current state.');
            }

            $targetPlan = Plan::query()->findOrFail($invoice->target_plan_id);
            $from = $subscription->status;
            [$startsAt, $endsAt] = $this->paidPeriod($subscription, $targetPlan, $paidAt);

            $subscription->forceFill([
                'plan_id' => $targetPlan->getKey(),
                'status' => SubscriptionStatus::Active,
                'starts_at' => BillingClock::storage($startsAt),
                'ends_at' => BillingClock::storage($endsAt),
            ])->save();
            $invoice->forceFill([
                'status' => InvoiceStatus::Paid,
                'paid_at' => BillingClock::storage($paidAt),
            ])->save();
            $payment->forceFill([
                'status' => BillingPaymentStatus::Paid,
                'verified_by_admin_id' => $actor->getKey(),
                'verified_at' => BillingClock::storage($paidAt),
                'failure_reason' => null,
            ])->save();

            $eventName = $from === SubscriptionStatus::Suspended ? 'transitioned' : 'extended';
            $this->events->execute($subscription, $eventName, $from, SubscriptionStatus::Active, $actor, [
                'invoice_id' => $invoice->getKey(), 'payment_id' => $payment->getKey(),
            ]);
            $this->audit->execute('billing.payment_verified', $actor, $payment, oldValues: ['status' => 'pending'], newValues: [
                'status' => 'paid', 'invoice_id' => $invoice->getKey(),
            ], context: $context, tenantId: $subscription->tenant_id);
            $this->audit->execute(
                $eventName === 'extended' ? 'billing.subscription_extended' : 'billing.subscription_transitioned',
                $actor,
                $subscription,
                oldValues: ['status' => $from->value],
                newValues: ['status' => 'active', 'ends_at' => $endsAt->toIso8601String()],
                context: $context,
                tenantId: $subscription->tenant_id,
            );

            return $payment->fresh();
        }, 3);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function paidPeriod(Subscription $subscription, Plan $plan, CarbonImmutable $paidAt): array
    {
        $oldEnd = CarbonImmutable::instance($subscription->ends_at)->setTimezone(BillingClock::BUSINESS_TIMEZONE);

        if ($subscription->status === SubscriptionStatus::Trial) {
            return [CarbonImmutable::instance($subscription->starts_at)->setTimezone(BillingClock::BUSINESS_TIMEZONE), $this->periods->end($oldEnd, $plan->billing_interval)];
        }
        if (in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
            return [CarbonImmutable::instance($subscription->starts_at)->setTimezone(BillingClock::BUSINESS_TIMEZONE), $this->periods->end($oldEnd, $plan->billing_interval)];
        }
        if ($subscription->status === SubscriptionStatus::Suspended) {
            return [$paidAt, $this->periods->end($paidAt, $plan->billing_interval)];
        }

        throw new ConflictHttpException('Expired subscription is terminal; create a new subscription.');
    }
}
