<?php

use App\Actions\Inventory\StockOutAction;
use App\Actions\Opname\CreateOpnameAction;
use App\Actions\Opname\FinalizeOpnameAction;
use App\Actions\Opname\SaveOpnameCountAction;
use App\Enums\StockOpnameStatus;
use App\Exceptions\ApiProblemException;
use App\Models\Rack;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

function makeOpnameRack(string $label): Rack
{
    return Rack::create(['kode' => "RAK-{$label}", 'nama' => "Rak {$label}"]);
}

it('freezes active scoped membership and permits drafts on different racks', function () {
    [, $owner] = makeTenantUser();
    $rackA = makeOpnameRack('A');
    $rackB = makeOpnameRack('B');
    $itemA = makeInventoryItem(['rack_id' => $rackA->id]);
    makeInventoryItem(['rack_id' => $rackB->id]);
    makeInventoryItem(['rack_id' => $rackA->id, 'is_active' => false]);

    $opnameA = app(CreateOpnameAction::class)->execute('partial', $owner, $rackA->id);
    $opnameB = app(CreateOpnameAction::class)->execute('partial', $owner, $rackB->id);

    expect($opnameA->details_count)->toBe(1)
        ->and($opnameA->details->pluck('item_id')->all())->toBe([$itemA->id])
        ->and($opnameB->status)->toBe(StockOpnameStatus::Draft);

    $itemA->update(['rack_id' => $rackB->id, 'is_active' => false]);
    $newItem = makeInventoryItem(['rack_id' => $rackA->id]);

    expect($opnameA->details()->pluck('item_id')->all())->toBe([$itemA->id])
        ->and($opnameA->details()->where('item_id', $newItem->id)->exists())->toBeFalse();
});

it('rejects same rack, full overlap, and empty scopes', function () {
    [, $owner] = makeTenantUser();
    $rackA = makeOpnameRack('A');
    $rackEmpty = makeOpnameRack('EMPTY');
    makeInventoryItem(['rack_id' => $rackA->id]);
    app(CreateOpnameAction::class)->execute('partial', $owner, $rackA->id);

    expect(fn () => app(CreateOpnameAction::class)->execute('partial', $owner, $rackA->id))
        ->toThrow(ApiProblemException::class, 'berkonflik');
    expect(fn () => app(CreateOpnameAction::class)->execute('full', $owner))
        ->toThrow(ApiProblemException::class, 'berkonflik');
    expect(fn () => app(CreateOpnameAction::class)->execute('partial', $owner, $rackEmpty->id))
        ->toThrow(ValidationException::class);
});

it('keeps the first snapshot while allowing physical count corrections', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 10]);
    $opname = app(CreateOpnameAction::class)->execute('full', $owner);

    $first = app(SaveOpnameCountAction::class)->execute($opname->id, [[
        'item_id' => $item->id, 'qty_fisik' => 8, 'note' => 'Hitungan pertama',
    ]], $owner)->first();
    app(StockOutAction::class)->execute($item->id, 1, $owner);
    $corrected = app(SaveOpnameCountAction::class)->execute($opname->id, [[
        'item_id' => $item->id, 'qty_fisik' => 7, 'note' => 'Koreksi',
    ]], $owner)->first();

    expect($first->qty_sistem_at_count)->toBe(10)
        ->and($corrected->qty_sistem_at_count)->toBe(10)
        ->and($corrected->counted_at->equalTo($first->counted_at))->toBeTrue()
        ->and($corrected->qty_fisik)->toBe(7);
});

it('performs time aware finalization without changing average cost', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 100, 'average_cost' => '42.50']);
    $opname = app(CreateOpnameAction::class)->execute('full', $owner);
    app(SaveOpnameCountAction::class)->execute($opname->id, [[
        'item_id' => $item->id, 'qty_fisik' => 98,
    ]], $owner);
    app(StockOutAction::class)->execute($item->id, 5, $owner);

    $result = app(FinalizeOpnameAction::class)->execute($opname->id, $owner);
    $movement = StockMovement::where('movement_type', 'opname_adjustment')->firstOrFail();

    expect($item->fresh()->stok_saat_ini)->toBe(93)
        ->and($item->fresh()->average_cost)->toBe('42.50')
        ->and($movement->qty)->toBe(2)
        ->and($movement->direction)->toBe('out')
        ->and($movement->reference_type)->toBe(StockOpname::class)
        ->and($movement->reference_id)->toBe($opname->id)
        ->and($result['summary']['total_units_out'])->toBe(2);
});

it('requires every detail and creates no movement for a zero delta', function () {
    [, $owner] = makeTenantUser();
    $itemA = makeInventoryItem(['stok_saat_ini' => 5]);
    makeInventoryItem(['stok_saat_ini' => 3]);
    $opname = app(CreateOpnameAction::class)->execute('full', $owner);
    app(SaveOpnameCountAction::class)->execute($opname->id, [[
        'item_id' => $itemA->id, 'qty_fisik' => 5,
    ]], $owner);

    expect(fn () => app(FinalizeOpnameAction::class)->execute($opname->id, $owner))
        ->toThrow(ApiProblemException::class, 'Semua detail');
    expect(StockMovement::where('movement_type', 'opname_adjustment')->count())->toBe(0);
});

it('honors negative stock policy and makes completed history immutable', function () {
    [, $owner] = makeTenantUser();
    $item = makeInventoryItem(['stok_saat_ini' => 2]);
    $opname = app(CreateOpnameAction::class)->execute('full', $owner);
    app(SaveOpnameCountAction::class)->execute($opname->id, [[
        'item_id' => $item->id, 'qty_fisik' => 0,
    ]], $owner);
    app(StockOutAction::class)->execute($item->id, 2, $owner);

    expect(fn () => app(FinalizeOpnameAction::class)->execute($opname->id, $owner))
        ->toThrow(ApiProblemException::class, 'negatif');
    expect($opname->fresh()->status)->toBe(StockOpnameStatus::Draft);

    TenantContext::get()->update(['allow_negative_stock' => true]);
    app(FinalizeOpnameAction::class)->execute($opname->id, $owner);

    expect($item->fresh()->stok_saat_ini)->toBe(-2)
        ->and(fn () => $opname->fresh()->update(['started_at' => now()->addDay()]))->toThrow(LogicException::class)
        ->and(fn () => StockOpnameDetail::firstOrFail()->update(['qty_fisik' => 1]))->toThrow(LogicException::class)
        ->and(fn () => app(FinalizeOpnameAction::class)->execute($opname->id, $owner))->toThrow(ApiProblemException::class);
});

it('fails closed for foreign tenant racks sessions and items', function () {
    [$tenantA, $ownerA] = makeTenantUser();
    $rackA = makeOpnameRack('A');
    $itemA = makeInventoryItem(['rack_id' => $rackA->id]);
    $opnameA = app(CreateOpnameAction::class)->execute('partial', $ownerA, $rackA->id);
    [, $ownerB] = makeTenantUser();
    $rackB = makeOpnameRack('B');
    $itemB = makeInventoryItem(['rack_id' => $rackB->id]);

    TenantContext::set($tenantA);
    expect(fn () => app(CreateOpnameAction::class)->execute('partial', $ownerA, $rackB->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(SaveOpnameCountAction::class)->execute($opnameA->id, [[
            'item_id' => $itemB->id, 'qty_fisik' => 1,
        ]], $ownerA))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(FinalizeOpnameAction::class)->execute($opnameA->id, $ownerB))->toThrow(ModelNotFoundException::class);

    expect($itemA->id)->not->toBe($itemB->id);
});
