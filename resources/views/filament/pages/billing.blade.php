<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Langganan saat ini">
            @if ($subscription)
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <dt class="text-gray-500">Plan</dt><dd>{{ $subscription->plan->name }}</dd>
                    <dt class="text-gray-500">Status</dt><dd class="font-semibold">{{ $subscription->status->value }}</dd>
                    <dt class="text-gray-500">Mulai</dt><dd>{{ $subscription->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</dd>
                    <dt class="text-gray-500">Berakhir</dt><dd>{{ $subscription->plan->is_internal ? 'Tidak berakhir (Legacy)' : $subscription->ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</dd>
                </dl>
            @else
                <p class="text-sm text-danger-600">Data subscription tidak tersedia. Hubungi support.</p>
            @endif
            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                @foreach ($capabilities as $name => $enabled)
                    <span @class(['rounded-full px-2 py-1', 'bg-success-100 text-success-700' => $enabled, 'bg-gray-100 text-gray-500' => ! $enabled])>{{ $name }}: {{ $enabled ? 'ya' : 'tidak' }}</span>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section heading="Penghapusan tenant">
            @if ($deletion && in_array($deletion->status->value, ['requested', 'approved', 'queued'], true))
                <p class="text-sm">Status: <strong>{{ $deletion->status->value }}</strong></p>
                <p class="mt-2 text-sm text-gray-600">{{ $deletion->reason }}</p>
                @if ($deletion->status->value === 'requested')
                    <x-filament::button class="mt-4" color="danger" wire:click="cancelDeletion" wire:confirm="Batalkan permintaan ini?">Batalkan permintaan</x-filament::button>
                @endif
            @else
                <form wire:submit="requestDeletion" class="space-y-3">
                    <label class="text-sm font-medium">Alasan penghapusan (10–1.000 karakter)</label>
                    <textarea wire:model="deletionReason" rows="4" class="w-full rounded-lg border-gray-300"></textarea>
                    @error('deletionReason') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    <x-filament::button type="submit" color="danger">Ajukan penghapusan</x-filament::button>
                </form>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section heading="Riwayat invoice">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="p-2">Nomor</th><th class="p-2">Plan</th><th class="p-2">Nominal</th><th class="p-2">Status</th><th class="p-2">Jatuh tempo</th></tr></thead>
                <tbody>
                @forelse ($invoices as $invoice)
                    <tr class="border-t"><td class="p-2">{{ $invoice->invoice_number }}</td><td class="p-2">{{ $invoice->targetPlan->name }}</td><td class="p-2">{{ \App\Support\Decimal::formatIdr($invoice->amount) }}</td><td class="p-2">{{ $invoice->status->value }}</td><td class="p-2">{{ $invoice->due_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td></tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center text-gray-500">Belum ada invoice.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
