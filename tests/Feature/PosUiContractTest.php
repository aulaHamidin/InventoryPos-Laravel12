<?php

use App\Enums\UserRole;
use App\Livewire\PosScreen;
use App\Models\User;
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
        ->get('/pos')
        ->assertOk()
        ->assertSee('BarcodeDetector', false)
        ->assertSee("event.key === 'F1'", false)
        ->assertSee("event.key === 'F2'", false)
        ->assertSee("event.key === 'Escape'", false)
        ->assertSee('wire:loading.attr="disabled"', false)
        ->assertSee('window.print()', false)
        ->assertSee('Bayar Tunai (F2)');

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

    $this->actingAs($staff)->get('/pos')->assertForbidden();
});
