<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\InvoiceStatus;
use App\Models\Admin;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\BillingClock;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class GenerateInvoiceAction
{
    public function __construct(private readonly BillingClock $clock, private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, Subscription $subscription, Plan $targetPlan, ?CarbonImmutable $dueAt = null, ?AuditContext $context = null): Invoice
    {
        AdminActorGuard::superAdmin($actor);
        $asOf = $this->clock->now();
        $dueAt ??= $asOf->addDays(7);

        return DB::transaction(function () use ($actor, $subscription, $targetPlan, $dueAt, $asOf, $context): Invoice {
            Tenant::query()->lockForUpdate()->findOrFail($subscription->tenant_id);
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $targetPlan = Plan::query()->findOrFail($targetPlan->getKey());
            if (! $targetPlan->is_active || $targetPlan->is_internal) {
                abort(422, 'Target plan is not available.');
            }
            if (Invoice::query()->where('subscription_id', $subscription->getKey())->where('status', InvoiceStatus::Open)->exists()) {
                throw new ConflictHttpException('An open invoice already exists.');
            }

            $invoice = Invoice::query()->create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->getKey(),
                'target_plan_id' => $targetPlan->getKey(),
                'invoice_number' => sprintf('INV-%s-%s', $asOf->format('Ym'), Str::ulid()),
                'amount' => Decimal::money($targetPlan->price),
                'due_at' => BillingClock::storage($dueAt),
                'status' => InvoiceStatus::Open,
            ]);
            $this->audit->execute('billing.invoice_created', $actor, $invoice, newValues: [
                'invoice_number' => $invoice->invoice_number,
                'target_plan_id' => $targetPlan->getKey(),
                'amount' => $invoice->amount,
            ], context: $context, tenantId: $subscription->tenant_id);

            return $invoice;
        }, 3);
    }
}
