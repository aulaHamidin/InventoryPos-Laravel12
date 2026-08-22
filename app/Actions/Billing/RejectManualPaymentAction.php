<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\BillingPaymentStatus;
use App\Models\Admin;
use App\Models\BillingPayment;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RejectManualPaymentAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, BillingPayment $payment, string $reason, ?AuditContext $context = null): BillingPayment
    {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $payment, $reason, $context): BillingPayment {
            Tenant::query()->lockForUpdate()->findOrFail($payment->tenant_id);
            Subscription::query()->lockForUpdate()->findOrFail($payment->subscription_id);
            Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            $payment = BillingPayment::query()->lockForUpdate()->findOrFail($payment->getKey());
            if ($payment->status === BillingPaymentStatus::Failed) {
                return $payment;
            }
            if ($payment->status !== BillingPaymentStatus::Pending) {
                throw new ConflictHttpException('Only pending payments may be rejected.');
            }
            $payment->forceFill(['status' => BillingPaymentStatus::Failed, 'failure_reason' => trim($reason)])->save();
            $this->audit->execute('billing.payment_rejected', $actor, $payment, oldValues: ['status' => 'pending'], newValues: [
                'status' => 'failed', 'failure_reason' => trim($reason),
            ], context: $context, tenantId: $payment->tenant_id);

            return $payment;
        }, 3);
    }
}
