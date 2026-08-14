<?php

use App\Actions\Inventory\AdjustStockAction;
use App\Actions\Inventory\SetPreferredSupplierAction;
use App\Actions\Inventory\StockInAction;
use App\Actions\Inventory\StockOutAction;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

it('calculates MAC and writes canonical immutable movement atomically', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 10, 'average_cost' => '100.00']);

    $movement = app(StockInAction::class)->execute($item->id, 10, '150.00', $owner);
    $item->refresh();

    expect($item->stok_saat_ini)->toBe(20)
        ->and($item->average_cost)->toBe('125.00')
        ->and($movement->movement_type)->toBe('stock_in')
        ->and($movement->user_id)->toBe($owner->id);

    expect(fn () => $movement->update(['qty' => 99]))->toThrow(RuntimeException::class);
});

it('enforces tenant negative stock policy for out and adjustment', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 2]);

    expect(fn () => app(StockOutAction::class)->execute($item->id, 3, $owner))
        ->toThrow(ValidationException::class);
    expect(fn () => app(AdjustStockAction::class)->execute($item->id, 3, 'out', 'opname', $owner))
        ->toThrow(ValidationException::class);

    expect($item->fresh()->stok_saat_ini)->toBe(2)
        ->and(StockMovement::count())->toBe(0);
});

it('allows negative stock only when tenant policy enables it', function () {
    [, $owner] = makeTenantUser(tenantAttributes: ['allow_negative_stock' => true]);
    $item = makeInventoryItem(['stok_saat_ini' => 1]);

    app(StockOutAction::class)->execute($item->id, 3, $owner);

    expect($item->fresh()->stok_saat_ini)->toBe(-2);
});

it('guards cross tenant IDs inside stock Actions', function () {
    [$tenantA, $ownerA] = makeTenantUser();
    $itemA = makeInventoryItem();
    [, $ownerB] = makeTenantUser();
    makeInventoryItem();

    TenantContext::set($tenantA);

    expect(fn () => app(StockInAction::class)->execute($itemA->id, 1, '10.00', $ownerB))
        ->toThrow(ModelNotFoundException::class);
});

it('sets one preferred supplier per item in a locked transaction', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem();
    $supplierA = Supplier::create(['nama' => 'Supplier A']);
    $supplierB = Supplier::create(['nama' => 'Supplier B']);
    $linkA = ItemSupplier::create(['item_id' => $item->id, 'supplier_id' => $supplierA->id]);
    $linkB = ItemSupplier::create(['item_id' => $item->id, 'supplier_id' => $supplierB->id]);

    app(SetPreferredSupplierAction::class)->execute($linkA->id, $owner);
    app(SetPreferredSupplierAction::class)->execute($linkB->id, $owner);

    expect(ItemSupplier::where('item_id', $item->id)->where('is_preferred', true)->count())->toBe(1)
        ->and($linkB->fresh()->is_preferred)->toBeTrue();
});

it('retains history when an item is deactivated or soft deleted', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem();
    app(StockInAction::class)->execute($item->id, 1, '60.00', $owner);
    $movementId = StockMovement::firstOrFail()->id;

    $item->delete();

    expect(Item::find($item->id))->toBeNull()
        ->and(Item::withTrashed()->find($item->id))->not->toBeNull()
        ->and(StockMovement::find($movementId)->item->id)->toBe($item->id);

    expect(fn () => $item->forceDelete())->toThrow(QueryException::class);
});
