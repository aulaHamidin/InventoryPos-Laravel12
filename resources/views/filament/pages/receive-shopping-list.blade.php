<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Penerimaan Barang
        </x-slot>
        <x-slot name="description">
            Silakan periksa barang yang datang dan cocokkan jumlah aktualnya dengan daftar belanja #{{ $shoppingList->id }}.
        </x-slot>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit" color="success" size="lg" icon="heroicon-o-check-circle">
                    Konfirmasi & Tambah Stok
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
