<x-filament-panels::page>
    @php
        $current = $this->currentDetail();
        $progress = $record->details_count > 0 ? round(($record->counted_details_count / $record->details_count) * 100) : 0;
    @endphp

    <div x-data="opnameScanner()" x-init="init()" class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm text-gray-500">
                        {{ $record->scope_type->label() }}
                        @if ($record->rack)
                            · {{ $record->rack->kode }} — {{ $record->rack->nama }}
                        @endif
                    </p>
                    <h2 class="text-xl font-bold text-gray-950 dark:text-white">
                        {{ $record->counted_details_count }} / {{ $record->details_count }} item dihitung
                    </h2>
                </div>
                <span class="inline-flex w-fit rounded-full px-3 py-1 text-sm font-semibold {{ $record->status === \App\Enums\StockOpnameStatus::Completed ? 'bg-success-100 text-success-700' : 'bg-warning-100 text-warning-700' }}">
                    {{ $record->status->label() }}
                </span>
            </div>
            <div class="mt-4 h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $progress }}%"></div>
            </div>
        </x-filament::section>

        @if ($record->status === \App\Enums\StockOpnameStatus::Completed && $completionSummary)
            <x-filament::section>
                <x-slot name="heading">Hasil Finalisasi</x-slot>
                <x-slot name="description">Histori ini bersifat read-only. Penyesuaian telah dicatat sebagai movement immutable.</x-slot>
                <dl class="grid grid-cols-2 gap-4 md:grid-cols-5">
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800"><dt class="text-xs text-gray-500">Total item</dt><dd class="text-2xl font-bold">{{ $completionSummary['item_count'] }}</dd></div>
                    <div class="rounded-xl bg-warning-50 p-4 dark:bg-warning-950"><dt class="text-xs text-gray-500">Disesuaikan</dt><dd class="text-2xl font-bold">{{ $completionSummary['adjusted_lines'] }}</dd></div>
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800"><dt class="text-xs text-gray-500">Tanpa selisih</dt><dd class="text-2xl font-bold">{{ $completionSummary['unchanged_lines'] }}</dd></div>
                    <div class="rounded-xl bg-success-50 p-4 dark:bg-success-950"><dt class="text-xs text-gray-500">Unit masuk</dt><dd class="text-2xl font-bold text-success-700">+{{ $completionSummary['total_units_in'] }}</dd></div>
                    <div class="rounded-xl bg-danger-50 p-4 dark:bg-danger-950"><dt class="text-xs text-gray-500">Unit keluar</dt><dd class="text-2xl font-bold text-danger-700">-{{ $completionSummary['total_units_out'] }}</dd></div>
                </dl>
            </x-filament::section>
        @endif

        @if ($record->status === \App\Enums\StockOpnameStatus::Draft && ! $showReview)
            <x-filament::section>
                <x-slot name="heading">Scan atau Cari Barang</x-slot>
                <div class="relative flex gap-2">
                    <input id="opname-barcode-input" type="text" wire:model.live.debounce.300ms="searchQuery"
                        wire:keydown.enter="handleBarcode($event.target.value)"
                        placeholder="Scan barcode atau cari nama/kode"
                        class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-base shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900">
                    <x-filament::button type="button" x-on:click="toggleScanner()" icon="heroicon-o-camera">
                        <span x-text="scannerOn ? 'Stop' : 'Kamera'"></span>
                    </x-filament::button>
                    @if ($searchResults !== [])
                        <div class="absolute inset-x-0 top-full z-20 mt-2 max-h-72 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900">
                            @foreach ($searchResults as $result)
                                <button type="button" wire:click="selectDetail({{ $result['id'] }})"
                                    class="flex w-full items-center justify-between border-b px-4 py-3 text-left hover:bg-primary-50 dark:border-gray-700 dark:hover:bg-primary-950">
                                    <span><strong>{{ $result['nama'] }}</strong><small class="block text-gray-500">{{ $result['kode'] }} · {{ $result['barcode'] ?: 'tanpa barcode' }}</small></span>
                                    <span class="text-xs font-semibold {{ $result['counted'] ? 'text-success-600' : 'text-warning-600' }}">{{ $result['counted'] ? 'Sudah dihitung' : 'Belum dihitung' }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div x-show="scannerOn" x-cloak class="mt-4 overflow-hidden rounded-xl bg-black">
                    <video x-ref="scannerVideo" class="h-64 w-full object-cover" muted playsinline></video>
                    <p class="p-2 text-center text-xs text-white">Arahkan barcode ke kamera. Input manual tetap tersedia sebagai fallback.</p>
                </div>
            </x-filament::section>

            @if ($current)
                <x-filament::section>
                    <div class="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
                        <div>
                            <p class="text-sm font-medium text-primary-600">{{ $current->item?->kode }}</p>
                            <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $current->item?->nama }}</h2>
                            <dl class="mt-5 grid grid-cols-2 gap-3">
                                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                                    <dt class="text-xs text-gray-500">Stok Sistem</dt>
                                    <dd class="text-2xl font-bold">{{ $current->qty_sistem_at_count ?? $current->item?->stok_saat_ini }}</dd>
                                </div>
                                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                                    <dt class="text-xs text-gray-500">Selisih</dt>
                                    <dd class="text-2xl font-bold {{ $qtyFisik !== '' && ((int) $qtyFisik - (int) ($current->qty_sistem_at_count ?? $current->item?->stok_saat_ini)) !== 0 ? 'text-warning-600' : 'text-success-600' }}">
                                        {{ $qtyFisik === '' ? '—' : ((int) $qtyFisik - (int) ($current->qty_sistem_at_count ?? $current->item?->stok_saat_ini)) }}
                                    </dd>
                                </div>
                            </dl>
                            @if ($current->counted_at)
                                <p class="mt-3 text-xs text-gray-500">Snapshot pertama: {{ $current->counted_at->format('d M Y H:i:s') }}. Koreksi tidak mengubah snapshot.</p>
                            @endif
                        </div>
                        <form wire:submit="saveAndNext" class="space-y-4">
                            <label class="block">
                                <span class="text-sm font-semibold">Stok Fisik</span>
                                <input id="physical-quantity" type="number" min="0" step="1" inputmode="numeric" autofocus
                                    wire:model.live.debounce.200ms="qtyFisik"
                                    class="mt-2 block w-full rounded-2xl border-2 border-primary-300 bg-white px-5 py-5 text-center text-4xl font-bold focus:border-primary-600 focus:ring-primary-600 dark:bg-gray-900">
                                @error('qtyFisik') <span class="mt-1 block text-sm text-danger-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium">Catatan opsional</span>
                                <textarea wire:model="note" rows="2" maxlength="1000" class="mt-2 block w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900"></textarea>
                            </label>
                            <x-filament::button type="submit" size="xl" class="w-full justify-center" wire:loading.attr="disabled" wire:target="saveAndNext">
                                <span wire:loading.remove wire:target="saveAndNext">Simpan & Next</span>
                                <span wire:loading wire:target="saveAndNext">Menyimpan...</span>
                            </x-filament::button>
                        </form>
                    </div>
                </x-filament::section>
            @endif
        @endif

        @if ($showReview || $record->status === \App\Enums\StockOpnameStatus::Completed)
            <x-filament::section>
                <x-slot name="heading">Review Selisih</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-xs uppercase text-gray-500"><tr><th class="px-3 py-3">Barang</th><th class="px-3 py-3 text-right">Sistem</th><th class="px-3 py-3 text-right">Fisik</th><th class="px-3 py-3 text-right">Selisih</th><th class="px-3 py-3">Status</th></tr></thead>
                        <tbody class="divide-y dark:divide-gray-700">
                            @foreach ($this->reviewDetails() as $detail)
                                @php($delta = $detail->counted_at ? $detail->qty_fisik - $detail->qty_sistem_at_count : null)
                                <tr wire:key="review-{{ $detail->id }}">
                                    <td class="px-3 py-3"><strong>{{ $detail->item?->nama }}</strong><small class="block text-gray-500">{{ $detail->item?->kode }}</small></td>
                                    <td class="px-3 py-3 text-right">{{ $detail->qty_sistem_at_count ?? '—' }}</td>
                                    <td class="px-3 py-3 text-right">{{ $detail->qty_fisik ?? '—' }}</td>
                                    <td class="px-3 py-3 text-right font-bold {{ $delta === 0 ? 'text-success-600' : 'text-warning-600' }}">{{ $delta === null ? '—' : ($delta > 0 ? '+' : '').$delta }}</td>
                                    <td class="px-3 py-3">
                                        @if ($detail->counted_at)
                                            <span class="text-success-600">Sudah dihitung</span>
                                            @if ($record->status === \App\Enums\StockOpnameStatus::Draft)
                                                <button type="button" wire:click="selectDetail({{ $detail->id }})" class="ml-2 font-semibold text-primary-600">Koreksi</button>
                                            @endif
                                        @else
                                            <span class="text-danger-600">Belum dihitung</span>
                                            <button type="button" wire:click="selectDetail({{ $detail->id }})" class="ml-2 font-semibold text-primary-600">Hitung</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($record->status === \App\Enums\StockOpnameStatus::Draft)
                    <div class="mt-6 flex flex-col justify-end gap-3 sm:flex-row">
                        <x-filament::button type="button" color="gray" wire:click="selectNextUncounted" wire:loading.attr="disabled">Kembali Menghitung</x-filament::button>
                        <x-filament::button type="button" color="success" wire:click="openFinalizeConfirmation" wire:loading.attr="disabled" :disabled="! $this->isComplete()">
                            Finalisasi Stock Opname
                        </x-filament::button>
                    </div>
                    @unless ($this->isComplete())
                        <p class="mt-2 text-right text-sm text-danger-600">Finalisasi tersedia setelah seluruh item dihitung.</p>
                    @endunless
                @endif
            </x-filament::section>
        @else
            <div class="flex justify-end">
                <x-filament::button type="button" color="gray" wire:click="openReview">Review Semua Item</x-filament::button>
            </div>
        @endif

        @if ($showFinalizeConfirmation)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="finalize-opname-title">
                <section class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900">
                    <h2 id="finalize-opname-title" class="text-xl font-bold">Finalisasi Stock Opname?</h2>
                    <p class="mt-3 text-gray-600 dark:text-gray-300">Sistem akan mencatat penyesuaian berdasarkan selisih stok sistem pada saat count dan stok fisik. Histori tidak dapat diedit setelah finalisasi.</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-filament::button type="button" color="gray" wire:click="$set('showFinalizeConfirmation', false)" wire:loading.attr="disabled">Batalkan</x-filament::button>
                        <x-filament::button type="button" color="success" wire:click="finalize" wire:loading.attr="disabled" wire:target="finalize">
                            <span wire:loading.remove wire:target="finalize">Finalisasi</span>
                            <span wire:loading wire:target="finalize">Memproses...</span>
                        </x-filament::button>
                    </div>
                </section>
            </div>
        @endif
    </div>

    <script>
        window.opnameScanner = window.opnameScanner || function () {
            return {
                scannerOn: false,
                stream: null,
                detector: null,
                init() { window.addEventListener('beforeunload', () => this.stopScanner()) },
                async toggleScanner() {
                    if (this.scannerOn) return this.stopScanner()
                    if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
                        document.getElementById('opname-barcode-input')?.focus()
                        alert('Scanner kamera tidak didukung. Gunakan scanner USB atau pencarian manual.')
                        return
                    }
                    try {
                        this.detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e'] })
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
                        this.$refs.scannerVideo.srcObject = this.stream
                        await this.$refs.scannerVideo.play()
                        this.scannerOn = true
                        requestAnimationFrame(() => this.scanFrame())
                    } catch (error) {
                        this.stopScanner()
                        alert('Kamera tidak dapat dibuka. Periksa izin browser.')
                    }
                },
                async scanFrame() {
                    if (!this.scannerOn) return
                    try {
                        const codes = await this.detector.detect(this.$refs.scannerVideo)
                        if (codes.length) {
                            this.stopScanner()
                            await this.$wire.handleBarcode(codes[0].rawValue)
                            document.getElementById('physical-quantity')?.focus()
                            return
                        }
                    } catch (error) {}
                    requestAnimationFrame(() => this.scanFrame())
                },
                stopScanner() {
                    this.stream?.getTracks().forEach(track => track.stop())
                    this.stream = null
                    this.scannerOn = false
                },
            }
        }
    </script>
</x-filament-panels::page>
