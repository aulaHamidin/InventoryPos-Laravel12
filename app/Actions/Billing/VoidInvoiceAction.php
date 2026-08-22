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

final class VoidInvoiceAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, Invoice $invoice, ?AuditContext $context = null): Invoice
    {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $invoice, $context): Invoice {
            Tenant::query()->lockForUpdate()->findOrFail($invoice->tenant_id);
            Subscription::query()->lockForUpdate()->findOrFail($invoice->subscription_id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            if ($invoice->status === InvoiceStatus::Void) {
                return $invoice;
            }
            if ($invoice->status !== InvoiceStatus::Open || BillingPayment::query()->where('invoice_id', $invoice->getKey())->where('status', BillingPaymentStatus::Paid)->exists()) {
                throw new ConflictHttpException('Paid invoice cannot be voided.');
            }
            $invoice->forceFill(['status' => InvoiceStatus::Void])->save();
            $this->audit->execute('billing.invoice_voided', $actor, $invoice, oldValues: ['status' => 'open'], newValues: ['status' => 'void'], context: $context, tenantId: $invoice->tenant_id);

            return $invoice;
        }, 3);
    }
}
