<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pos\CheckoutPosAction;
use App\Actions\Pos\PayCashAction;
use App\Http\Controllers\Controller;
use App\Models\PosTransaction;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'total_amount' => $result['transaction']->total_amount,
            'cash_received' => $result['cash_received'],
            'change_amount' => $result['change'],
        ], 'Pembayaran tunai berhasil.');
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $transaction = OwnershipGuard::validate(PosTransaction::class, $id);
        $this->authorize('view', $transaction);
        $payment = $transaction->payments()->latest('id')->first();

        return $this->success([
            'transaction_id' => $transaction->id,
            'transaction_status' => $transaction->status->value,
            'payment' => $payment ? [
                'method' => $payment->method->value,
                'status' => $payment->status->value,
            ] : null,
        ]);
    }
}
