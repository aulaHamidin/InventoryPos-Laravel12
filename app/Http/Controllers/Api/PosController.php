<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pos\CheckoutPosAction;
use App\Actions\Pos\ConfirmManualPaymentAction;
use App\Actions\Pos\MarkPosPaymentRefundedAction;
use App\Actions\Pos\PayCashAction;
use App\Actions\Pos\ReturnPosTransactionAction;
use App\Actions\Pos\VoidPosTransactionAction;
use App\Http\Controllers\Controller;
use App\Models\PosPayment;
use App\Models\PosTransaction;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use App\Support\PosRefundCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function checkout(Request $request, CheckoutPosAction $action): JsonResponse
    {
        $this->authorize('create', PosTransaction::class);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'], 'items.*.item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.discount_amount' => ['nullable', 'decimal:0,2', 'min:0'],
        ]);

        $transaction = $action->execute(
            $data['items'],
            trim((string) $request->header('Idempotency-Key')),
            $request->user(),
            AuditContext::fromRequest($request),
        );

        return $this->success([
            'id' => $transaction->id,
            'invoice_number' => $transaction->invoice_number,
            'status' => $transaction->status->value,
            'subtotal_amount' => $transaction->subtotal_amount,
            'discount_amount' => $transaction->discount_amount,
            'total_amount' => $transaction->total_amount,
            'items' => $transaction->items,
        ], 'Checkout berhasil.', 201);
    }

    public function payCash(Request $request, PayCashAction $action, int $id): JsonResponse
    {
        $this->authorize('update', OwnershipGuard::validate(PosTransaction::class, $id));
        $data = $request->validate(['cash_received' => ['required', 'decimal:0,2', 'min:0']]);
        $result = $action->execute($id, $data['cash_received'], $request->user(), AuditContext::fromRequest($request));

        return $this->success([
            'transaction_id' => $result['transaction']->id,
            'status' => $result['transaction']->status->value,
            'transaction_status' => $result['transaction']->status->value,
            'payment_status' => $result['payment']->status->value,
            'method' => $result['payment']->method->value,
            'amount' => $result['payment']->amount,
            'total_amount' => $result['transaction']->total_amount,
            'cash_received' => $result['cash_received'],
            'change_amount' => $result['change'],
            'requires_refund' => false,
        ], 'Pembayaran tunai berhasil.');
    }

    public function payManual(Request $request, ConfirmManualPaymentAction $action, int $id): JsonResponse
    {
        $this->authorize('update', OwnershipGuard::validate(PosTransaction::class, $id));
        $data = $request->validate([
            'method' => ['required', 'string', 'in:qris,transfer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'amount' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'confirmed_by' => ['prohibited'],
            'status' => ['prohibited'],
        ]);
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if (! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['Idempotency-Key wajib berupa UUID yang valid.'],
            ]);
        }

        $result = $action->execute(
            $id,
            $data['method'],
            $idempotencyKey,
            $request->user(),
            $data['reference'] ?? null,
            $data['note'] ?? null,
            AuditContext::fromRequest($request),
        );

        return $this->success([
            'transaction_id' => $result['transaction']->id,
            'transaction_status' => $result['transaction']->status->value,
            'payment_status' => $result['payment']->status->value,
            'method' => $result['payment']->method->value,
            'method_label' => $result['payment']->method->label(),
            'amount' => $result['payment']->amount,
            'confirmed_by' => $result['payment']->confirmedBy ? [
                'id' => $result['payment']->confirmedBy->id,
                'name' => $result['payment']->confirmedBy->name,
            ] : null,
            'reference' => $result['payment']->manual_reference,
            'note' => $result['payment']->confirmation_note,
            'paid_at' => $result['payment']->paid_at?->toIso8601String(),
            'requires_refund' => $result['requires_refund'],
            'refund_obligation_amount' => $result['refund_obligation_amount'],
            'refund_due_amount' => $result['refund_due_amount'],
        ], $result['requires_refund']
            ? 'Pembayaran tercatat, tetapi stok gagal difinalisasi dan refund wajib diproses.'
            : 'Pembayaran manual berhasil dikonfirmasi.');
    }

    public function void(Request $request, VoidPosTransactionAction $action, int $id): JsonResponse
    {
        $this->authorize('update', OwnershipGuard::validate(PosTransaction::class, $id));
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $result = $action->execute($id, $data['reason'], $request->user(), AuditContext::fromRequest($request));

        return $this->success($this->refundOperationPayload($result), 'Transaksi dibatalkan dan refund wajib dicatat.');
    }

    public function returnItems(Request $request, ReturnPosTransactionAction $action, int $id): JsonResponse
    {
        $this->authorize('update', OwnershipGuard::validate(PosTransaction::class, $id));
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.pos_transaction_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);
        $result = $action->execute($id, $data['items'], $request->user(), AuditContext::fromRequest($request));

        return $this->success(array_merge($this->refundOperationPayload($result), [
            'refund_delta_amount' => $result['refund_delta_amount'],
        ]), 'Retur berhasil dicatat.');
    }

    public function markRefunded(Request $request, MarkPosPaymentRefundedAction $action, int $id): JsonResponse
    {
        /** @var PosPayment $payment */
        $payment = OwnershipGuard::validate(PosPayment::class, $id);
        $this->authorize('update', $payment);
        $data = $request->validate([
            'refunded_amount' => ['required', 'decimal:0,2', 'min:0'],
            'note' => ['required', 'string', 'max:1000'],
        ]);
        $result = $action->execute(
            $id,
            $data['refunded_amount'],
            $data['note'],
            $request->user(),
            AuditContext::fromRequest($request),
        );

        return $this->success(array_merge($this->refundOperationPayload($result), [
            'no_op' => $result['no_op'],
        ]), $result['no_op'] ? 'Nilai refund tidak berubah.' : 'Refund berhasil dicatat.');
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $transaction = OwnershipGuard::validate(PosTransaction::class, $id);
        $this->authorize('view', $transaction);
        $payment = $transaction->payments()->with('confirmedBy')->latest('id')->first();
        if ($payment !== null) {
            $transaction->load('items');
            $payment->setRelation('transaction', $transaction);
        }

        return $this->success([
            'transaction_id' => $transaction->id,
            'transaction_status' => $transaction->status->value,
            'payment' => $payment ? [
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'confirmed_by' => $payment->confirmedBy ? [
                    'id' => $payment->confirmedBy->id,
                    'name' => $payment->confirmedBy->name,
                ] : null,
                'reference' => $payment->manual_reference,
                'note' => $payment->confirmation_note,
                'confirmed_at' => $payment->paid_at?->toIso8601String(),
                'refunded_amount' => $payment->refunded_amount,
                'refund_obligation_amount' => PosRefundCalculator::obligation($payment),
                'refund_due_amount' => PosRefundCalculator::due($payment),
            ] : null,
        ]);
    }

    private function refundOperationPayload(array $result): array
    {
        return [
            'transaction_id' => $result['transaction']->id,
            'transaction_status' => $result['transaction']->status->value,
            'payment_id' => $result['payment']->id,
            'payment_status' => $result['payment']->status->value,
            'refunded_amount' => $result['payment']->refunded_amount,
            'refund_obligation_amount' => $result['refund_obligation_amount'],
            'refund_due_amount' => $result['refund_due_amount'],
        ];
    }
}
