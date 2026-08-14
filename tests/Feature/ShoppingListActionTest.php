<?php

use App\Actions\Inventory\SetPreferredSupplierAction;
use App\Actions\Shopping\GenerateShoppingListAction;
use App\Actions\Shopping\ReceiveShoppingListAction;
use App\Actions\Shopping\SubmitShoppingListAction;
use App\Enums\ShoppingListStatus;
use App\Models\ItemSupplier;
use App\Models\ShoppingList;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Validation\ValidationException;

it('does not create an empty shopping list', function () {
    [, $owner] = makeTenantUser();
    makeInventoryItem(['stok_saat_ini' => 10, 'stok_minimal' => 2]);

    expect(app(GenerateShoppingListAction::class)->execute($owner))->toBeNull()
        ->and(ShoppingList::count())->toBe(0);
});

it('generates per item preferred or null supplier with canonical recommendation', function () {
    [, $owner] = makeTenantUser();
    $preferredItem = makeInventoryItem(['stok_saat_ini' => 2, 'stok_minimal' => 5]);
    $nullItem = makeInventoryItem(['stok_saat_ini' => 5, 'stok_minimal' => 5]);
    $supplier = Supplier::create(['nama' => 'Preferred']);
    $link = ItemSupplier::create(['item_id' => $preferredItem->id, 'supplier_id' => $supplier->id]);
    app(SetPreferredSupplierAction::class)->execute($link->id, $owner);

    $list = app(GenerateShoppingListAction::class)->execute($owner);
    $rows = $list->items->keyBy('item_id');

    expect($list->status)->toBe(ShoppingListStatus::Draft)
        ->and($rows[$preferredItem->id]->supplier_id)->toBe($supplier->id)
        ->and($rows[$preferredItem->id]->qty_disarankan)->toBe(3)
        ->and($rows[$nullItem->id]->supplier_id)->toBeNull()
        ->and($rows[$nullItem->id]->qty_disarankan)->toBe(1);
});

it('validates submit and completes a one time atomic receive', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 1, 'stok_minimal' => 5, 'average_cost' => '50.00']);
    $supplier = Supplier::create(['nama' => 'Supplier']);
    $list = app(GenerateShoppingListAction::class)->execute($owner);
    $listItem = $list->items->first();

    expect(fn () => app(SubmitShoppingListAction::class)->execute($list->id, [[
        'shopping_list_item_id' => $listItem->id,
        'is_checked' => true,
        'supplier_id' => null,
        'qty_dibeli' => 4,
    ]], $owner))->toThrow(ValidationException::class);

    $purchased = app(SubmitShoppingListAction::class)->execute($list->id, [[
        'shopping_list_item_id' => $listItem->id,
        'is_checked' => true,
        'supplier_id' => $supplier->id,
        'qty_dibeli' => 4,
    ]], $owner);

    expect($purchased->status)->toBe(ShoppingListStatus::Purchased);

    $completed = app(ReceiveShoppingListAction::class)->execute($list->id, [[
        'shopping_list_item_id' => $listItem->id,
        'qty_received' => 3,
        'harga_satuan' => '80.00',
    ]], $owner);

    expect($completed->status)->toBe(ShoppingListStatus::Completed)
        ->and($item->fresh()->stok_saat_ini)->toBe(4)
        ->and($listItem->fresh()->qty_received)->toBe(3)
        ->and(StockMovement::where('reference_type', ShoppingList::class)->count())->toBe(1);

    expect(fn () => app(ReceiveShoppingListAction::class)->execute($list->id, [[
        'shopping_list_item_id' => $listItem->id,
        'qty_received' => 3,
        'harga_satuan' => '80.00',
    ]], $owner))->toThrow(ValidationException::class);
});
