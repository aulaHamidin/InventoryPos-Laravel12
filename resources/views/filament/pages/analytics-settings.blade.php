<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Pengaturan Analytics</x-slot>
        <x-slot name="description">
            Atur aging dead stock dalam zona bisnis Asia/Jakarta. Nilai ini berlaku untuk seluruh barang tenant.
        </x-slot>

        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit" icon="heroicon-o-check" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Simpan Pengaturan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
