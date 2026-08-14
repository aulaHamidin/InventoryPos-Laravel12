<?php

use App\Actions\Inventory\StockInAction;
use App\Actions\MasterData\DeleteMasterDataAction;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Rack;
use App\Models\Supplier;
use Illuminate\Validation\ValidationException;

it('deletes only unused master data through an audited integrity guard', function () {
    [, $owner] = makeTenantUser();
    $rack = Rack::create(['kode' => 'R-01', 'nama' => 'Rak Terpakai']);
    $item = makeInventoryItem(['rack_id' => $rack->id]);
    $supplier = Supplier::create(['nama' => 'Supplier Historis']);
    app(StockInAction::class)->execute($item->id, 1, '55.00', $owner, $supplier->id);

    $action = app(DeleteMasterDataAction::class);
    expect(fn () => $action->execute(Category::class, $item->category_id, $owner))
        ->toThrow(ValidationException::class);
    expect(fn () => $action->execute(Rack::class, $rack->id, $owner))
        ->toThrow(ValidationException::class);
    expect(fn () => $action->execute(Supplier::class, $supplier->id, $owner))
        ->toThrow(ValidationException::class);

    $unused = Supplier::create(['nama' => 'Supplier Tidak Terpakai']);
    $action->execute(Supplier::class, $unused->id, $owner);

    expect(Supplier::find($unused->id))->toBeNull()
        ->and(AuditLog::where('action', 'master.deleted')->count())->toBe(1);
});
