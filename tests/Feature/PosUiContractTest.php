<?php

use App\Enums\UserRole;
use App\Livewire\PosScreen;
use App\Models\User;
use App\Services\TenantContext;
use Livewire\Livewire;

it('renders the Owner POS scanner and keyboard contract while denying Staff', function () {
    [, $owner] = makeTenantUser();
    $staff = User::create([
        'name' => 'POS Staff',
        'email' => 'pos-staff@example.test',
        'no_hp' => '083333333333',
        'password' => 'password',
        'role' => UserRole::Staff,
    ]);

    $this->actingAs($owner)
        ->get('/app/pos')
        ->assertOk()
        ->assertSee('BarcodeDetector', false)
        ->assertSee("event.key === 'F1'", false)
        ->assertSee("event.key === 'F2'", false)
        ->assertSee("event.key === 'Escape'", false)
        ->assertSee("event.key === 'Delete'", false)
        ->assertSee('wire:loading.attr="disabled"', false)
        ->assertSee('window.print()', false)
        ->assertSee('Pilih Pembayaran')
        ->assertDontSee('Bluetooth Beta');

    // Livewire update requests run through /livewire/update instead of /app/pos.
    // The persistent middleware must restore the tenant from the session user.
    TenantContext::clear();

    Livewire::actingAs($owner)
        ->test(PosScreen::class)
        ->set('cart', [[
            'item_id' => 1, 'nama' => 'Test', 'kode' => 'TEST', 'harga_jual' => '100.00',
            'qty' => 1, 'discount_amount' => '0.00', 'subtotal' => '100.00', 'stok_tersedia' => 1,
        ]])
        ->set('showPaymentModal', true)
        ->assertSee('QRIS Statis')
        ->assertSee('Transfer Bank')
        ->set('paymentMethod', 'qris')
        ->assertSee('Saya telah memastikan dana diterima')
        ->assertSee('tidak diverifikasi otomatis oleh bank');

    Livewire::actingAs($owner)
        ->test(PosScreen::class)
        ->set('showReceipt', true)
        ->set('completedTransaction', [
            'invoice_number' => 'INV-PRINT-TEST',
            'subtotal_amount' => 10000,
            'discount_amount' => 0,
            'total_amount' => 10000,
            'cash_received' => 10000,
            'change' => 0,
            'items' => [],
            'cashier' => $owner->name,
            'date' => now()->format('d/m/Y H:i'),
        ])
        ->assertSee('Cetak Struk');

    $this->actingAs($staff)->get('/app/pos')->assertForbidden();
});
