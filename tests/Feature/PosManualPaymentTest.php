<?php

use App\Actions\Pos\CheckoutPosAction;
use App\Actions\Pos\ConfirmManualPaymentAction;
use App\Actions\Pos\ExpirePendingPosTransactionAction;
use App\Actions\Pos\MarkPosPaymentRefundedAction;
use App\Actions\Pos\PayCashAction;
use App\Actions\Pos\ReturnPosTransactionAction;
use App\Actions\Pos\VoidPosTransactionAction;
use App\Enums\PosPaymentMethod;
use App\Enums\PosPaymentStatus;
use App\Enums\PosTransactionStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiProblemException;
use App\Models\AuditLog;
use App\Models\PosPayment;
use App\Models\PosTransaction;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\PosRefundRequired;
use App\Services\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function manualCheckout($owner, $item, int $qty = 1, string $discount = '0.00'): PosTransaction
{
    return app(CheckoutPosAction::class)->execute([[
        'item_id' => $item->id,
        'qty' => $qty,
        'discount_amount' => $discount,
    ]], (string) Str::uuid(), $owner);
}

it('confirms QRIS statis and transfer from the backend total with normalized manual metadata', function (string $method) {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 5, 'harga_jual' => '125.00']);
    $transaction = manualCheckout($owner, $item, 2, '10.00');
    $key = (string) Str::uuid();

    $first = app(ConfirmManualPaymentAction::class)->execute(
        $transaction->id, $method, $key, $owner, '  REF-001  ', '  Diperiksa merchant  ',
    );
    $second = app(ConfirmManualPaymentAction::class)->execute(
        $transaction->id, $method, $key, $owner, 'REF-001', 'Diperiksa merchant',
    );

    expect($first['transaction']->status)->toBe(PosTransactionStatus::Completed)
        ->and($first['payment']->method)->toBe(PosPaymentMethod::from($method))
        ->and($first['payment']->status)->toBe(PosPaymentStatus::Paid)
        ->and($first['payment']->amount)->toBe('240.00')
        ->and($first['payment']->confirmed_by)->toBe($owner->id)
        ->and($first['payment']->manual_reference)->toBe('REF-001')
        ->and($first['payment']->confirmation_note)->toBe('Diperiksa merchant')
        ->and($first['payment']->paid_at)->not->toBeNull()
        ->and($second['payment']->id)->toBe($first['payment']->id)
        ->and(PosPayment::count())->toBe(1)
        ->and(StockMovement::where('movement_type', 'sale')->count())->toBe(1)
        ->and($item->fresh()->stok_saat_ini)->toBe(3);
})->with(['qris', 'transfer']);

it('records received manual funds as refund required when stock fails without creating sale movement', function () {
    Notification::fake();
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 2, 'harga_jual' => '100.00']);
    $transaction = manualCheckout($owner, $item, 2);
    $item->update(['stok_saat_ini' => 0]);

    $result = app(ConfirmManualPaymentAction::class)->execute(
        $transaction->id, 'qris', (string) Str::uuid(), $owner, null, 'Dana masuk',
    );

    expect($result['transaction']->status)->toBe(PosTransactionStatus::RefundRequired)
        ->and($result['payment']->status)->toBe(PosPaymentStatus::RefundRequired)
        ->and($result['payment']->paid_at)->not->toBeNull()
        ->and($result['requires_refund'])->toBeTrue()
        ->and($result['refund_obligation_amount'])->toBe('200.00')
        ->and($result['refund_due_amount'])->toBe('200.00')
        ->and(StockMovement::count())->toBe(0)
        ->and($item->fresh()->stok_saat_ini)->toBe(0);
    Notification::assertSentTo($owner, PosRefundRequired::class);

    expect(fn () => app(PayCashAction::class)->execute($transaction->id, '200.00', $owner))
        ->toThrow(ApiProblemException::class);
});

it('returns idempotency conflict when one manual key is reused across transactions or payloads', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 10]);
    $first = manualCheckout($owner, $item);
    $second = manualCheckout($owner, $item);
    $key = (string) Str::uuid();
    $action = app(ConfirmManualPaymentAction::class);
    $action->execute($first->id, 'qris', $key, $owner, null, null);

    foreach ([
        fn () => $action->execute($second->id, 'qris', $key, $owner, null, null),
        fn () => $action->execute($first->id, 'transfer', $key, $owner, null, null),
        fn () => $action->execute($first->id, 'qris', $key, $owner, 'DIFFERENT', null),
    ] as $attempt) {
        try {
            $attempt();
            $this->fail('Expected idempotency conflict.');
        } catch (ApiProblemException $exception) {
            expect($exception->errorCode)->toBe('IDEMPOTENCY_CONFLICT')
                ->and($exception->httpStatus)->toBe(409);
        }
    }
});

it('uses an inclusive 24 hour cutoff for payment and scheduler expiry without side effects', function () {
    $now = CarbonImmutable::parse('2026-08-15 12:00:00');
    Carbon::setTestNow($now);
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 5]);

    $before = manualCheckout($owner, $item);
    $before->forceFill(['created_at' => $now->subHours(24)->addSecond()])->save();
    $paid = app(PayCashAction::class)->execute($before->id, '100.00', $owner);
    expect($paid['transaction']->status)->toBe(PosTransactionStatus::Completed);

    $boundary = manualCheckout($owner, $item);
    $boundary->forceFill(['created_at' => $now->subHours(24)])->save();
    expect(app(ExpirePendingPosTransactionAction::class)->execute($boundary->id))->toBeTrue()
        ->and($boundary->fresh()->status)->toBe(PosTransactionStatus::Expired)
        ->and($boundary->payments()->count())->toBe(0)
        ->and(StockMovement::where('reference_id', $boundary->id)->count())->toBe(0)
        ->and(AuditLog::where('subject_id', $boundary->id)->where('action', 'pos.expired')->count())->toBe(1);

    try {
        app(ConfirmManualPaymentAction::class)->execute(
            $boundary->id, 'transfer', (string) Str::uuid(), $owner,
        );
        $this->fail('Expected expired transaction rejection.');
    } catch (ApiProblemException $exception) {
        expect($exception->errorCode)->toBe('TRANSACTION_ALREADY_PROCESSED');
    }
    Carbon::setTestNow();
});

it('voids every payment method with full obligation and immutable sale void movements', function (string $method) {
    Notification::fake();
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 5, 'harga_jual' => '100.00']);
    $transaction = manualCheckout($owner, $item, 2);
    if ($method === 'cash') {
        app(PayCashAction::class)->execute($transaction->id, '200.00', $owner);
    } else {
        app(ConfirmManualPaymentAction::class)->execute($transaction->id, $method, (string) Str::uuid(), $owner);
    }

    $result = app(VoidPosTransactionAction::class)->execute($transaction->id, 'Salah transaksi', $owner);

    expect($result['transaction']->status)->toBe(PosTransactionStatus::Voided)
        ->and($result['payment']->status)->toBe(PosPaymentStatus::RefundRequired)
        ->and($result['refund_obligation_amount'])->toBe('200.00')
        ->and($result['refund_due_amount'])->toBe('200.00')
        ->and($item->fresh()->stok_saat_ini)->toBe(5)
        ->and(StockMovement::where('movement_type', 'sale_void')->count())->toBe(1);
})->with(['cash', 'qris', 'transfer']);

it('calculates exact cumulative partial return refunds and can reopen due after a settled obligation', function () {
    Notification::fake();
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 10, 'harga_jual' => '100.00']);
    $transaction = manualCheckout($owner, $item, 3, '0.01');
    app(ConfirmManualPaymentAction::class)->execute($transaction->id, 'transfer', (string) Str::uuid(), $owner);
    $lineId = $transaction->items()->firstOrFail()->id;
    $returns = app(ReturnPosTransactionAction::class);

    $first = $returns->execute($transaction->id, [[
        'pos_transaction_item_id' => $lineId, 'qty' => 1,
    ]], $owner);
    expect($first['refund_delta_amount'])->toBe('99.99')
        ->and($first['refund_obligation_amount'])->toBe('99.99')
        ->and($first['refund_due_amount'])->toBe('99.99');

    $settled = app(MarkPosPaymentRefundedAction::class)->execute(
        $first['payment']->id, '99.99', 'Refund pertama selesai', $owner,
    );
    expect($settled['payment']->status)->toBe(PosPaymentStatus::PartiallyRefunded)
        ->and($settled['refund_due_amount'])->toBe('0.00');

    expect(fn () => app(VoidPosTransactionAction::class)->execute(
        $transaction->id, 'Tidak boleh setelah retur', $owner,
    ))->toThrow(ApiProblemException::class);
    foreach (['99.98', '100.00'] as $invalidTarget) {
        try {
            app(MarkPosPaymentRefundedAction::class)->execute(
                $first['payment']->id, $invalidTarget, 'Target invalid', $owner,
            );
            $this->fail('Expected cumulative refund validation failure.');
        } catch (ApiProblemException $exception) {
            expect($exception->errorCode)->toBe(
                $invalidTarget === '99.98' ? 'REFUND_AMOUNT_DECREASED' : 'REFUND_AMOUNT_EXCEEDED',
            );
        }
    }
    $noOp = app(MarkPosPaymentRefundedAction::class)->execute(
        $first['payment']->id, '99.99', 'No-op aman', $owner,
    );
    expect($noOp['no_op'])->toBeTrue();

    $second = $returns->execute($transaction->id, [[
        'pos_transaction_item_id' => $lineId, 'qty' => 1,
    ]], $owner);
    expect($second['refund_delta_amount'])->toBe('99.99')
        ->and($second['refund_obligation_amount'])->toBe('199.98')
        ->and($second['refund_due_amount'])->toBe('99.99');

    $final = $returns->execute($transaction->id, [[
        'pos_transaction_item_id' => $lineId, 'qty' => 1,
    ]], $owner);
    expect($final['refund_delta_amount'])->toBe('100.01')
        ->and($final['refund_obligation_amount'])->toBe('299.99')
        ->and($final['transaction']->status)->toBe(PosTransactionStatus::FullyReturned)
        ->and($item->fresh()->stok_saat_ini)->toBe(10)
        ->and((int) StockMovement::where('movement_type', 'customer_return')->sum('qty'))->toBe(3);

    $refunded = app(MarkPosPaymentRefundedAction::class)->execute(
        $final['payment']->id, '299.99', 'Refund penuh selesai', $owner,
    );
    expect($refunded['payment']->status)->toBe(PosPaymentStatus::Refunded)
        ->and($refunded['refund_due_amount'])->toBe('0.00');
    $refundedNoOp = app(MarkPosPaymentRefundedAction::class)->execute(
        $final['payment']->id, '299.99', 'Repeat full refund no-op', $owner,
    );
    expect($refundedNoOp['no_op'])->toBeTrue();
});

it('serializes manual payment and refund amounts through the API without trusting a client amount', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['harga_jual' => '150.00']);
    Sanctum::actingAs($owner);

    $checkout = $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/pos/checkout', ['items' => [[
            'item_id' => $item->id, 'qty' => 1, 'discount_amount' => '10.00',
        ]]])->assertCreated();
    $id = $checkout->json('data.id');

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/pos/transactions/{$id}/pay-manual", [
            'method' => 'qris', 'reference' => ' =SUM(A1:A2) ', 'note' => '<script>alert(1)</script>',
        ])->assertOk()
        ->assertJsonPath('data.transaction_status', 'completed')
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.method', 'qris')
        ->assertJsonPath('data.amount', '140.00')
        ->assertJsonPath('data.reference', '=SUM(A1:A2)');

    $this->getJson("/api/v1/pos/transactions/{$id}/status")
        ->assertOk()
        ->assertJsonPath('data.payment.method_label', 'QRIS Statis')
        ->assertJsonPath('data.payment.note', '<script>alert(1)</script>')
        ->assertJsonPath('data.payment.refund_due_amount', '0.00');

    TenantContext::set($owner->tenant);
    $pending = manualCheckout($owner, $item);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/pos/transactions/{$pending->id}/pay-manual", [
            'method' => 'transfer', 'amount' => '1.00',
        ])->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('amount');
});

it('normalizes blank metadata and rejects invalid manual requests at the API boundary', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem();
    Sanctum::actingAs($owner);
    $transaction = manualCheckout($owner, $item);

    $this->postJson("/api/v1/pos/transactions/{$transaction->id}/pay-manual", [
        'method' => 'cash',
    ])->assertUnprocessable()->assertJsonValidationErrors('method');
    $this->postJson("/api/v1/pos/transactions/{$transaction->id}/pay-manual", [
        'method' => 'qris',
    ])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/pos/transactions/{$transaction->id}/pay-manual", [
            'method' => 'qris', 'reference' => str_repeat('R', 256), 'note' => str_repeat('N', 1001),
        ])->assertUnprocessable()->assertJsonValidationErrors(['reference', 'note']);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/pos/transactions/{$transaction->id}/pay-manual", [
            'method' => 'transfer', 'reference' => '   ', 'note' => " \n ",
        ])->assertOk()
        ->assertJsonPath('data.reference', null)
        ->assertJsonPath('data.note', null);
});

it('hides cross tenant POS and payment IDs as 404 and denies Staff financial actions', function () {
    [$tenantA, $ownerA] = makeTenantUser();
    $staffA = User::create([
        'name' => 'POS Staff', 'email' => 'pos-staff-a@example.test', 'no_hp' => '081111111110',
        'password' => 'password', 'role' => UserRole::Staff,
    ]);
    $itemA = makeInventoryItem();
    $transactionA = manualCheckout($ownerA, $itemA);
    $resultA = app(ConfirmManualPaymentAction::class)->execute(
        $transactionA->id, 'qris', (string) Str::uuid(), $ownerA,
    );

    [, $ownerB] = makeTenantUser();
    Sanctum::actingAs($ownerB);
    $this->getJson("/api/v1/pos/transactions/{$transactionA->id}/status")->assertNotFound();
    $this->postJson("/api/v1/pos/payments/{$resultA['payment']->id}/mark-refunded", [
        'refunded_amount' => '0.00', 'note' => 'Cross tenant',
    ])->assertNotFound();

    TenantContext::set($tenantA);
    Sanctum::actingAs($staffA);
    $pending = manualCheckout($ownerA, $itemA);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/pos/transactions/{$pending->id}/pay-manual", ['method' => 'qris'])
        ->assertForbidden();
    $this->getJson("/api/v1/pos/transactions/{$transactionA->id}/status")->assertForbidden();
});

it('escapes stored manual notes on the Owner transaction detail', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem();
    $transaction = manualCheckout($owner, $item);
    app(ConfirmManualPaymentAction::class)->execute(
        $transaction->id,
        'qris',
        (string) Str::uuid(),
        $owner,
        '<img src=x onerror=alert(1)>',
        '<script>alert(1)</script>',
    );

    $this->actingAs($owner)
        ->get("/app/pos-transactions/{$transaction->id}")
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false);
});
