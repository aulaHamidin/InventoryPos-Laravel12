<?php

namespace App\Support;

use App\Models\PosPayment;
use App\Models\PosTransaction;

final class PosReceiptFormatter
{
    public static function format(
        PosTransaction $transaction,
        PosPayment $payment,
        array $items,
        string $cashier,
        ?string $cashReceived = null,
        ?string $change = null,
    ): array {
        $payment->setRelation('transaction', $transaction->loadMissing('items'));

        return [
            'transaction_id' => $transaction->getKey(),
            'invoice_number' => $transaction->invoice_number,
            'transaction_status' => $transaction->status->value,
            'subtotal_amount' => (float) $transaction->subtotal_amount,
            'discount_amount' => (float) $transaction->discount_amount,
            'total_amount' => (float) $transaction->total_amount,
            'payment_method' => $payment->method->value,
            'payment_method_label' => $payment->method->label(),
            'payment_status' => $payment->status->value,
            'manual_confirmation_label' => $payment->method->isManual() ? 'Dikonfirmasi Manual' : null,
            'manual_reference' => $payment->manual_reference,
            'confirmation_note' => $payment->confirmation_note,
            'confirmed_at' => $payment->paid_at?->format('d/m/Y H:i'),
            'cash_received' => $cashReceived === null ? null : (float) $cashReceived,
            'change' => $change === null ? null : (float) $change,
            'requires_refund' => $payment->status->value === 'refund_required',
            'refund_obligation_amount' => (float) PosRefundCalculator::obligation($payment),
            'refund_due_amount' => (float) PosRefundCalculator::due($payment),
            'items' => $items,
            'cashier' => $cashier,
            'date' => now()->format('d/m/Y H:i'),
        ];
    }
}
