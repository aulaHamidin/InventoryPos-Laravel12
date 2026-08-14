<div class="min-h-screen bg-gray-100 dark:bg-gray-950" x-data="posKeyboard()" x-init="init()" @keydown.window="handleKeydown($event)">
    @if ($feedback)
        <div class="fixed right-4 top-4 z-[100] rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-xl
            {{ $feedbackType === 'error' ? 'bg-danger-600' : ($feedbackType === 'warning' ? 'bg-warning-600' : 'bg-success-600') }}">
            {{ $feedback }}
        </div>
    @endif

    @if ($showReceipt && $completedTransaction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <section id="transaction-receipt" class="iq-print-surface w-full max-w-md rounded-2xl bg-white p-7 shadow-2xl dark:bg-gray-800">
                <h2 class="text-center text-xl font-bold text-gray-900 dark:text-white">Transaksi selesai</h2>
                <p class="mt-1 text-center text-sm text-gray-500">{{ $completedTransaction['invoice_number'] }}</p>
                <div class="mt-5 space-y-2 border-y border-dashed border-gray-300 py-4 dark:border-gray-600">
                    @foreach ($completedTransaction['items'] as $item)
                        <div class="flex justify-between text-sm">
                            <span>{{ $item['nama'] }} x {{ $item['qty'] }}</span>
                            <span>Rp{{ number_format($item['subtotal'], 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt>Bruto</dt><dd>Rp{{ number_format($completedTransaction['subtotal_amount'], 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt>Diskon</dt><dd>-Rp{{ number_format($completedTransaction['discount_amount'], 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between text-lg font-bold text-primary-600"><dt>Total</dt><dd>Rp{{ number_format($completedTransaction['total_amount'], 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt>Tunai</dt><dd>Rp{{ number_format($completedTransaction['cash_received'], 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between font-semibold text-success-600"><dt>Kembalian</dt><dd>Rp{{ number_format($completedTransaction['change'], 0, ',', '.') }}</dd></div>
                </dl>
                <div class="iq-no-print mt-6 grid grid-cols-2 gap-3">
                    <button type="button" @click="printReceipt()"
                        class="w-full rounded-xl bg-gray-200 py-3 font-bold text-gray-900 dark:bg-gray-700 dark:text-white">
                        Cetak Struk
                    </button>
                    <button wire:click="newTransaction" wire:loading.attr="disabled" class="w-full rounded-xl bg-primary-600 py-3 font-bold text-white disabled:opacity-50">
                        Transaksi Baru
                    </button>
                </div>
            </section>
        </div>
    @endif

    @if ($showPaymentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <section class="w-full max-w-md rounded-2xl bg-white p-7 shadow-2xl dark:bg-gray-800">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Pembayaran tunai</h2>
                <div class="my-5 rounded-xl bg-primary-50 p-4 dark:bg-primary-950">
                    <p class="text-sm text-primary-600">Total net</p>
                    <p class="text-3xl font-bold text-primary-700 dark:text-primary-300">Rp{{ number_format($this->cartTotal, 0, ',', '.') }}</p>
                </div>
                <label class="text-sm font-medium">Uang diterima</label>
                <input id="cash-input" type="number" min="0" step="0.01" wire:model.live="cashReceived"
                    class="mt-2 w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3 text-center text-2xl font-bold dark:border-gray-600 dark:bg-gray-700"
                    autofocus>
                <div class="my-5 rounded-xl bg-success-50 p-4 dark:bg-success-950">
                    <p class="text-sm text-success-600">Kembalian</p>
                    <p class="text-2xl font-bold text-success-700">Rp{{ number_format((float) $change, 0, ',', '.') }}</p>
                </div>
                <div class="flex gap-3">
                    <button wire:click="cancelPayment" wire:loading.attr="disabled"
                        class="flex-1 rounded-xl bg-gray-200 py-3 font-semibold dark:bg-gray-700">Batal (Esc)</button>
                    <button wire:click="processCashPayment" wire:loading.attr="disabled" @disabled($processingPayment || (float) $cashReceived < $this->cartTotal)
                        class="flex-1 rounded-xl bg-success-600 py-3 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">
                        <span wire:loading.remove wire:target="processCashPayment">Bayar</span>
                        <span wire:loading wire:target="processCashPayment">Memproses...</span>
                    </button>
                </div>
            </section>
        </div>
    @endif

    <main class="flex min-h-screen flex-col lg:flex-row">
        <section class="flex min-w-0 flex-1 flex-col p-4 lg:p-6">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <input id="barcode-input" type="text" wire:model.live.debounce.300ms="searchQuery"
                        wire:keydown.enter="handleBarcode($event.target.value)"
                        placeholder="Scan barcode atau cari barang (F1)"
                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-4 text-lg shadow dark:border-gray-700 dark:bg-gray-800">
                    @if (count($searchResults) > 0)
                        <div class="absolute inset-x-0 top-full z-40 mt-2 max-h-80 overflow-y-auto rounded-xl border bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                            @foreach ($searchResults as $result)
                                <button wire:click="addToCart({{ $result['id'] }})" wire:loading.attr="disabled"
                                    class="flex w-full justify-between border-b px-4 py-3 text-left hover:bg-primary-50 dark:border-gray-700 dark:hover:bg-primary-950">
                                    <span><strong>{{ $result['nama'] }}</strong><small class="block text-gray-500">{{ $result['kode'] }} - stok {{ $result['stok_saat_ini'] }}</small></span>
                                    <span class="font-semibold text-primary-600">Rp{{ number_format($result['harga_jual'], 0, ',', '.') }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <button type="button" @click="toggleScanner()" class="rounded-xl bg-primary-600 px-4 font-semibold text-white">
                    <span x-text="scannerOn ? 'Stop Kamera' : 'Kamera'"></span>
                </button>
            </div>

            <div x-show="scannerOn" x-cloak class="mt-4 overflow-hidden rounded-2xl bg-black">
                <video x-ref="scannerVideo" class="h-64 w-full object-cover" muted playsinline></video>
                <p class="bg-gray-900 p-2 text-center text-xs text-white">Scanner kamera aktif secara berkelanjutan. Arahkan barcode ke kamera.</p>
            </div>

            <div class="flex flex-1 items-center justify-center py-12 text-center text-gray-400" x-show="!scannerOn">
                <div>
                    <p class="text-lg font-semibold">F1 scan / fokus barcode</p>
                    <p class="mt-1 text-sm">F2 bayar, Esc membatalkan dialog pembayaran</p>
                </div>
            </div>
        </section>

        <aside class="flex w-full flex-col border-t bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800 lg:w-[460px] lg:border-l lg:border-t-0">
            <header class="border-b p-5 dark:border-gray-700">
                <h1 class="text-lg font-bold">Keranjang <span class="text-sm font-normal text-gray-500">({{ count($cart) }} baris)</span></h1>
            </header>
            <div class="max-h-[55vh] flex-1 space-y-3 overflow-y-auto p-4 lg:max-h-none">
                @forelse ($cart as $index => $item)
                    <article wire:key="cart-{{ $item['item_id'] }}" class="rounded-xl bg-gray-50 p-4 dark:bg-gray-700/60">
                        <div class="flex justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ $item['nama'] }}</p>
                                <p class="text-xs text-gray-500">Rp{{ number_format((float) $item['harga_jual'], 0, ',', '.') }} / {{ $item['kode'] }}</p>
                            </div>
                            <button wire:click="removeFromCart({{ $index }})" wire:loading.attr="disabled" class="text-danger-600" aria-label="Hapus item">Hapus</button>
                        </div>
                        <div class="mt-3 grid grid-cols-[auto_1fr] gap-3">
                            <div class="flex items-center gap-2">
                                <button wire:click="updateQty({{ $index }}, {{ $item['qty'] - 1 }})" wire:loading.attr="disabled" class="h-8 w-8 rounded-lg bg-gray-200 dark:bg-gray-600">-</button>
                                <strong class="w-8 text-center">{{ $item['qty'] }}</strong>
                                <button wire:click="updateQty({{ $index }}, {{ $item['qty'] + 1 }})" wire:loading.attr="disabled" class="h-8 w-8 rounded-lg bg-gray-200 dark:bg-gray-600">+</button>
                            </div>
                            <label class="text-xs text-gray-500">
                                Diskon baris
                                <input type="number" min="0" step="0.01" value="{{ $item['discount_amount'] }}"
                                    wire:change="updateDiscount({{ $index }}, $event.target.value)"
                                    class="mt-1 w-full rounded-lg border-gray-300 bg-white px-2 py-1 text-right dark:bg-gray-700">
                            </label>
                        </div>
                        <div class="mt-3 flex justify-between border-t pt-2 text-sm dark:border-gray-600">
                            <span>Net baris</span><strong>Rp{{ number_format((float) $item['subtotal'], 0, ',', '.') }}</strong>
                        </div>
                    </article>
                @empty
                    <p class="py-12 text-center text-sm text-gray-400">Keranjang kosong</p>
                @endforelse
            </div>
            <footer class="space-y-2 border-t bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex justify-between text-sm"><span>Bruto</span><span>Rp{{ number_format($this->cartGross, 0, ',', '.') }}</span></div>
                <div class="flex justify-between text-sm text-danger-600"><span>Diskon</span><span>-Rp{{ number_format($this->cartDiscount, 0, ',', '.') }}</span></div>
                <div class="flex justify-between text-xl font-bold text-primary-600"><span>Total net</span><span>Rp{{ number_format($this->cartTotal, 0, ',', '.') }}</span></div>
                <button wire:click="openPayment" wire:loading.attr="disabled" @disabled(empty($cart))
                    class="mt-3 w-full rounded-xl bg-primary-600 py-4 text-lg font-bold text-white disabled:opacity-50">
                    Bayar Tunai (F2)
                </button>
            </footer>
        </aside>
    </main>

    <script>
        window.posKeyboard = window.posKeyboard || function () {
            return {
                scannerOn: false,
                stream: null,
                detector: null,
                lastCode: null,
                lastScanAt: 0,
                init() {
                    window.addEventListener('beforeunload', () => this.stopScanner());
                },
                async toggleScanner() {
                    if (this.scannerOn) {
                        this.stopScanner();
                        return;
                    }
                    if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
                        document.getElementById('barcode-input')?.focus();
                        alert('Scanner kamera tidak didukung browser ini. Gunakan scanner USB atau input barcode.');
                        return;
                    }
                    try {
                        this.detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e'] });
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                        this.$refs.scannerVideo.srcObject = this.stream;
                        await this.$refs.scannerVideo.play();
                        this.scannerOn = true;
                        requestAnimationFrame(() => this.scanFrame());
                    } catch (error) {
                        this.stopScanner();
                        alert('Kamera tidak dapat dibuka. Periksa izin browser.');
                    }
                },
                stopScanner() {
                    this.scannerOn = false;
                    this.stream?.getTracks().forEach(track => track.stop());
                    this.stream = null;
                },
                printReceipt() {
                    document.body.classList.add('printing-receipt');
                    window.addEventListener('afterprint', () => document.body.classList.remove('printing-receipt'), { once: true });
                    window.print();
                },
                async scanFrame() {
                    if (!this.scannerOn) return;
                    try {
                        const codes = await this.detector.detect(this.$refs.scannerVideo);
                        const raw = codes[0]?.rawValue;
                        const now = Date.now();
                        if (raw && (raw !== this.lastCode || now - this.lastScanAt > 1500)) {
                            this.lastCode = raw;
                            this.lastScanAt = now;
                            this.$wire.handleBarcode(raw);
                        }
                    } catch (error) {
                        // Keep scanning; transient decode failures are expected.
                    }
                    requestAnimationFrame(() => this.scanFrame());
                },
                handleKeydown(event) {
                    if (event.key === 'F1') {
                        event.preventDefault();
                        document.getElementById('barcode-input')?.focus();
                    } else if (event.key === 'F2') {
                        event.preventDefault();
                        this.$wire.openPayment();
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        this.$wire.cancelPayment();
                    }
                }
            };
        };
    </script>
</div>
