<?php

use App\Actions\Pos\CheckoutPosAction;
use App\Actions\Pos\PayCashAction;
use App\Enums\PosTransactionStatus;
use App\Events\ItemAnalyticsRecalculationRequested;
use App\Exceptions\ApiProblemException;
use App\Models\PosPayment;
use App\Models\PosTransaction;
use App\Models\StockMovement;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

it('merges duplicate items and calculates gross discount and net separately', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['harga_jual' => '100.00', 'stok_saat_ini' => 20]);

    $transaction = app(CheckoutPosAction::class)->execute([
        ['item_id' => $item->id, 'qty' => 2, 'discount_amount' => '10.00'],
        ['item_id' => $item->id, 'qty' => 3, 'discount_amount' => '15.00'],
    ], (string) Str::uuid(), $owner);

    expect($transaction->items)->toHaveCount(1)
        ->and($transaction->items->first()->qty)->toBe(5)
        ->and($transaction->subtotal_amount)->toBe('500.00')
        ->and($transaction->discount_amount)->toBe('25.00')
        ->and($transaction->total_amount)->toBe('475.00')
        ->and($item->fresh()->stok_saat_ini)->toBe(20);
});

it('rejects a line discount greater than its gross amount', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['harga_jual' => '100.00']);

    expect(fn () => app(CheckoutPosAction::class)->execute([
        ['item_id' => $item->id, 'qty' => 1, 'discount_amount' => '100.01'],
    ], (string) Str::uuid(), $owner))->toThrow(ValidationException::class);

    expect(PosTransaction::count())->toBe(0);
});

it('is idempotent for the same payload and rejects a different payload for the same key', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem();
    $key = (string) Str::uuid();
    $checkout = app(CheckoutPosAction::class);

    $first = $checkout->execute([['item_id' => $item->id, 'qty' => 1, 'discount_amount' => 0]], $key, $owner);
    $second = $checkout->execute([['item_id' => $item->id, 'qty' => 1, 'discount_amount' => '0.00']], $key, $owner);

    expect($second->id)->toBe($first->id)
        ->and(PosTransaction::count())->toBe(1);

    try {
        $checkout->execute([['item_id' => $item->id, 'qty' => 2, 'discount_amount' => 0]], $key, $owner);
        $this->fail('Expected idempotency conflict.');
    } catch (ApiProblemException $exception) {
        expect($exception->errorCode)->toBe('IDEMPOTENCY_CONFLICT')
            ->and($exception->httpStatus)->toBe(409);
    }
});

it('pays total amount, returns change, deducts once, and blocks a second payment', function () {
    Event::fake([ItemAnalyticsRecalculationRequested::class]);
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 10, 'harga_jual' => '100.00']);
    $transaction = app(CheckoutPosAction::class)->execute([
        ['item_id' => $item->id, 'qty' => 2, 'discount_amount' => '20.00'],
    ], (string) Str::uuid(), $owner);

    $result = app(PayCashAction::class)->execute($transaction->id, '250.00', $owner);

    expect($result['payment']->amount)->toBe('180.00')
        ->and($result['change'])->toBe('70.00')
        ->and($result['transaction']->status)->toBe(PosTransactionStatus::Completed)
        ->and($item->fresh()->stok_saat_ini)->toBe(8)
        ->and(StockMovement::where('movement_type', 'sale')->count())->toBe(1);
    Event::assertDispatched(ItemAnalyticsRecalculationRequested::class, fn ($event): bool => $event->reason === 'sale' && $event->itemIds === [$item->id]);

    expect(fn () => app(PayCashAction::class)->execute($transaction->id, '250.00', $owner))
        ->toThrow(ApiProblemException::class);
    expect(PosPayment::count())->toBe(1);
});

it('commits failed transaction state when stock changes before payment', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 5]);
    $transaction = app(CheckoutPosAction::class)->execute([
        ['item_id' => $item->id, 'qty' => 5, 'discount_amount' => 0],
    ], (string) Str::uuid(), $owner);

    $item->update(['stok_saat_ini' => 0]);

    try {
        app(PayCashAction::class)->execute($transaction->id, '1000.00', $owner);
        $this->fail('Expected insufficient stock.');
    } catch (ApiProblemException $exception) {
        expect($exception->errorCode)->toBe('INSUFFICIENT_STOCK');
    }

    expect($transaction->fresh()->status)->toBe(PosTransactionStatus::Failed)
        ->and(PosPayment::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);
});

it('creates globally unique invoices across tenants', function () {
    [$tenantA, $ownerA] = makeTenantUser();
    $itemA = makeInventoryItem();
    $invoiceA = app(CheckoutPosAction::class)->execute([
        ['item_id' => $itemA->id, 'qty' => 1, 'discount_amount' => 0],
    ], (string) Str::uuid(), $ownerA)->invoice_number;

    [, $ownerB] = makeTenantUser();
    $itemB = makeInventoryItem();
    $invoiceB = app(CheckoutPosAction::class)->execute([
        ['item_id' => $itemB->id, 'qty' => 1, 'discount_amount' => 0],
    ], (string) Str::uuid(), $ownerB)->invoice_number;

    expect($invoiceA)->not->toBe($invoiceB);
    TenantContext::set($tenantA);
});

it('rejects a zero net transaction because payment amount must be positive', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['harga_jual' => '100.00']);

    expect(fn () => app(CheckoutPosAction::class)->execute([
        ['item_id' => $item->id, 'qty' => 1, 'discount_amount' => '100.00'],
    ], (string) Str::uuid(), $owner))->toThrow(ValidationException::class);

    expect(PosTransaction::count())->toBe(0);
});

it('honors the tenant negative stock policy during cash payment revalidation', function () {
    [$tenant, $owner] = makeTenantUser(tenantAttributes: ['allow_negative_stock' => true]);
    $item = makeInventoryItem(['stok_saat_ini' => 1, 'harga_jual' => '100.00']);

    $transaction = app(CheckoutPosAction::class)->execute([
        ['item_id' => $item->id, 'qty' => 2, 'discount_amount' => '0.00'],
    ], (string) Str::uuid(), $owner);

    $result = app(PayCashAction::class)->execute($transaction->id, '200.00', $owner);

    expect($result['transaction']->status)->toBe(PosTransactionStatus::Completed)
        ->and($item->fresh()->stok_saat_ini)->toBe(-1)
        ->and(PosPayment::count())->toBe(1);
});
