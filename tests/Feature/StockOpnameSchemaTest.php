<?php

use App\Actions\Opname\CreateOpnameAction;
use App\Models\Rack;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('enforces scope count-field and unique-detail constraints in the database', function () {
    [$tenant, $owner] = makeTenantUser();
    $rack = Rack::create(['kode' => 'DB', 'nama' => 'Rak DB']);
    $item = makeInventoryItem(['rack_id' => $rack->id]);
    $now = now();

    expect(fn () => DB::table('stock_opnames')->insert([
        'tenant_id' => $tenant->id,
        'created_by' => $owner->id,
        'scope_type' => 'full',
        'rack_id' => $rack->id,
        'status' => 'draft',
        'started_at' => $now,
    ]))->toThrow(QueryException::class);

    $opname = app(CreateOpnameAction::class)->execute('partial', $owner, $rack->id);
    expect(fn () => DB::table('stock_opname_details')->insert([
        'tenant_id' => $tenant->id,
        'stock_opname_id' => $opname->id,
        'item_id' => $item->id,
        'qty_sistem_at_count' => 10,
        'qty_fisik' => null,
        'counted_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('stock_opname_details')->insert([
        'tenant_id' => $tenant->id,
        'stock_opname_id' => $opname->id,
        'item_id' => $item->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]))->toThrow(QueryException::class);
});
