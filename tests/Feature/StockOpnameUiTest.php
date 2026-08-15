<?php

use App\Actions\Opname\CreateOpnameAction;
use App\Filament\Resources\StockOpnameResource\Pages\CountStockOpname;
use App\Models\Rack;
use App\Services\TenantContext;
use Livewire\Livewire;

it('renders the responsive scanner count review and confirmation contract', function () {
    [, $owner] = makeTenantUser();
    $rack = Rack::create(['kode' => 'UI', 'nama' => 'Rak UI']);
    $item = makeInventoryItem(['rack_id' => $rack->id, 'barcode' => '899000000001']);
    $opname = app(CreateOpnameAction::class)->execute('partial', $owner, $rack->id);

    $this->actingAs($owner)->get("/app/stock-opnames/{$opname->id}/count")
        ->assertOk()
        ->assertSee('Simpan & Next', false)
        ->assertSee('BarcodeDetector', false);

    TenantContext::clear();
    TenantContext::set($owner->tenant);
    Livewire::actingAs($owner)->test(CountStockOpname::class, ['record' => $opname->id])
        ->set('qtyFisik', '8')
        ->call('saveAndNext')
        ->assertSet('showReview', true)
        ->assertSee('Review Selisih')
        ->call('openFinalizeConfirmation')
        ->assertSet('showFinalizeConfirmation', true)
        ->call('finalize')
        ->assertSee('Hasil Finalisasi')
        ->assertDontSee('Simpan & Next');

    expect($item->fresh()->stok_saat_ini)->toBe(8);
});
