<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\BillingPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Admin;
use App\Models\BillingPayment;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RecordManualPaymentAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, Invoice $invoice, ?string $reference = null, ?AuditContext $context = null): BillingPayment
    {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $invoice, $reference, $context): BillingPayment {
            Tenant::query()->lockForUpdate()->findOrFail($invoice->tenant_id);
            Subscription::query()->lockForUpdate()->findOrFail($invoice->subscription_id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            abort_unless($invoice->status === InvoiceStatus::Open, 409, 'Invoice is not open.');
            if (BillingPayment::query()->where('invoice_id', $invoice->getKey())->where('status', BillingPaymentStatus::Pending)->exists()) {
                throw new ConflictHttpException('A pending payment already exists for this invoice.');
            }

            $payment = BillingPayment::query()->create([
                'tenant_id' => $invoice->tenant_id,
                'subscription_id' => $invoice->subscription_id,
                'invoice_id' => $invoice->getKey(),
                'amount' => $invoice->amount,
                'status' => BillingPaymentStatus::Pending,
                'provider' => 'manual',
                'provider_reference' => $reference !== null ? trim($reference) : null,
                'recorded_by_admin_id' => $actor->getKey(),
            ]);
            $this->audit->execute('billing.payment_recorded', $actor, $payment, newValues: [
                'invoice_id' => $invoice->getKey(), 'amount' => $invoice->amount, 'status' => 'pending',
                'reference_hint' => $reference ? substr(hash('sha256', $reference), 0, 12) : null,
            ], context: $context, tenantId: $invoice->tenant_id);

            return $payment;
        }, 3);
    }
}
