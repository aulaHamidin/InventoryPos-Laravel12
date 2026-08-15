<?php

namespace App\Livewire;

use App\Actions\Pos\CheckoutPosAction;
use App\Actions\Pos\ConfirmManualPaymentAction;
use App\Actions\Pos\PayCashAction;
use App\Exceptions\ApiProblemException;
use App\Models\Item;
use App\Models\PosTransaction;
use App\Support\AuditContext;
use App\Support\PosReceiptFormatter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PosScreen extends Component
{
    public array $cart = [];

    public string $searchQuery = '';

    public array $searchResults = [];

    public bool $showPaymentModal = false;

    public string $cashReceived = '0';

    public string $change = '0';

    public string $paymentMethod = 'cash';

    public string $manualReference = '';

    public string $confirmationNote = '';

    public bool $manualConfirmed = false;

    public bool $showReceipt = false;

    public ?array $completedTransaction = null;

    public string $feedback = '';

    public string $feedbackType = 'success';

    public string $idempotencyKey = '';

    public string $paymentIdempotencyKey = '';

    public bool $processingPayment = false;

    public bool $bluetoothPrintEnabled = false;

    public ?int $selectedCartIndex = null;

    protected $listeners = ['scanBarcode' => 'handleBarcode'];

    public function boot(): void
    {
        Gate::authorize('create', PosTransaction::class);
    }

    public function mount(): void
    {
        $this->bluetoothPrintEnabled = (bool) config('pos.bluetooth_print_enabled', false);
        $this->resetCart();
    }

    public function updatedSearchQuery(): void
    {
        if (mb_strlen($this->searchQuery) < 2) {
            $this->searchResults = [];

            return;
        }

        $search = $this->searchQuery;
        $this->searchResults = Item::where('is_active', true)
            ->where(fn ($query) => $query->where('nama', 'like', "%{$search}%")
                ->orWhere('kode', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%"))
            ->limit(10)->get()->toArray();
    }

    public function handleBarcode(string $barcode): void
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return;
        }

        $item = Item::where('is_active', true)
            ->where(fn ($query) => $query->where('barcode', $barcode)
                ->orWhere('kode', $barcode)
                ->orWhere('nama', 'like', "%{$barcode}%"))
            ->orderByRaw('barcode = ? DESC, kode = ? DESC', [$barcode, $barcode])
            ->first();
        if ($item === null) {
            $this->setFeedback("Barcode '{$barcode}' tidak ditemukan.", 'error');

            return;
        }

        $this->addToCart((int) $item->getKey());
    }

    public function addToCart(int $itemId): void
    {
        $item = Item::whereKey($itemId)->where('is_active', true)->first();
        if ($item === null) {
            $this->setFeedback('Barang tidak ditemukan atau tidak aktif.', 'error');

            return;
        }

        $index = array_search($itemId, array_column($this->cart, 'item_id'), true);
        if ($index !== false) {
            $this->cart[$index]['qty']++;
            $this->recalculateLine($index);
            $this->selectedCartIndex = $index;
        } else {
            $this->cart[] = [
                'item_id' => $item->getKey(), 'nama' => $item->nama, 'kode' => $item->kode,
                'harga_jual' => (string) $item->harga_jual, 'qty' => 1,
                'discount_amount' => '0.00', 'subtotal' => (string) $item->harga_jual,
                'stok_tersedia' => $item->stok_saat_ini,
            ];
            $this->selectedCartIndex = count($this->cart) - 1;
        }

        $this->renewAttempt();
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->setFeedback("{$item->nama} ditambahkan.");
    }

    public function updateQty(int $index, int $qty): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }
        if ($qty <= 0) {
            $this->removeFromCart($index);

            return;
        }

        $this->cart[$index]['qty'] = $qty;
        $this->selectedCartIndex = $index;
        $this->recalculateLine($index);
        $this->renewAttempt();
    }

    public function updateDiscount(int $index, mixed $discount): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        $gross = (float) $this->cart[$index]['harga_jual'] * $this->cart[$index]['qty'];
        $value = max(0, (float) $discount);
        if ($value > $gross) {
            $value = $gross;
            $this->setFeedback('Diskon dibatasi sebesar nilai bruto baris.', 'warning');
        }

        $this->cart[$index]['discount_amount'] = number_format($value, 2, '.', '');
        $this->selectedCartIndex = $index;
        $this->recalculateLine($index);
        $this->renewAttempt();
    }

    public function removeFromCart(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->selectedCartIndex = $this->cart === [] ? null : min($index, count($this->cart) - 1);
        $this->renewAttempt();
    }

    public function selectCartItem(int $index): void
    {
        if (isset($this->cart[$index])) {
            $this->selectedCartIndex = $index;
        }
    }

    public function incrementSelected(): void
    {
        if ($this->selectedCartIndex !== null && isset($this->cart[$this->selectedCartIndex])) {
            $this->updateQty($this->selectedCartIndex, $this->cart[$this->selectedCartIndex]['qty'] + 1);
        }
    }

    public function decrementSelected(): void
    {
        if ($this->selectedCartIndex !== null && isset($this->cart[$this->selectedCartIndex])) {
            $this->updateQty($this->selectedCartIndex, $this->cart[$this->selectedCartIndex]['qty'] - 1);
        }
    }

    public function removeSelected(): void
    {
        if ($this->selectedCartIndex !== null) {
            $this->removeFromCart($this->selectedCartIndex);
        }
    }

    public function getCartGrossProperty(): float
    {
        return array_sum(array_map(fn (array $line): float => (float) $line['harga_jual'] * $line['qty'], $this->cart));
    }

    public function getCartDiscountProperty(): float
    {
        return array_sum(array_map(fn (array $line): float => (float) $line['discount_amount'], $this->cart));
    }

    public function getCartTotalProperty(): float
    {
        return max(0, $this->cartGross - $this->cartDiscount);
    }

    public function openPayment(): void
    {
        if ($this->cart === []) {
            $this->setFeedback('Keranjang masih kosong.', 'error');

            return;
        }

        $this->cashReceived = number_format($this->cartTotal, 2, '.', '');
        $this->updatedCashReceived();
        $this->paymentMethod = 'cash';
        $this->manualReference = '';
        $this->confirmationNote = '';
        $this->manualConfirmed = false;
        $this->paymentIdempotencyKey = (string) Str::uuid();
        $this->showPaymentModal = true;
    }

    public function updatedCashReceived(): void
    {
        $this->change = number_format(max(0, (float) $this->cashReceived - $this->cartTotal), 2, '.', '');
    }

    public function updatedPaymentMethod(): void
    {
        $this->manualConfirmed = false;
    }

    public function cancelPayment(): void
    {
        $this->showPaymentModal = false;
    }

    public function processCashPayment(): void
    {
        $this->paymentMethod = 'cash';
        $this->processPayment();
    }

    public function processPayment(): void
    {
        if ($this->processingPayment) {
            return;
        }
        if (! in_array($this->paymentMethod, ['cash', 'qris', 'transfer'], true)) {
            $this->setFeedback('Metode pembayaran tidak valid.', 'error');

            return;
        }
        if ($this->paymentMethod === 'cash' && (float) $this->cashReceived < $this->cartTotal) {
            $this->setFeedback('Uang yang diterima kurang dari total.', 'error');

            return;
        }
        if ($this->paymentMethod !== 'cash' && ! $this->manualConfirmed) {
            $this->setFeedback('Pastikan dana benar-benar telah diterima sebelum konfirmasi.', 'error');

            return;
        }

        $this->processingPayment = true;

        try {
            $items = array_map(fn (array $line): array => [
                'item_id' => $line['item_id'],
                'qty' => $line['qty'],
                'discount_amount' => $line['discount_amount'],
            ], $this->cart);

            $transaction = app(CheckoutPosAction::class)->execute(
                $items, $this->idempotencyKey, auth()->user(), AuditContext::fromRequest(request()),
            );
            $result = $this->paymentMethod === 'cash'
                ? app(PayCashAction::class)->execute(
                    (int) $transaction->getKey(), $this->cashReceived, auth()->user(), AuditContext::fromRequest(request()),
                )
                : app(ConfirmManualPaymentAction::class)->execute(
                    (int) $transaction->getKey(),
                    $this->paymentMethod,
                    $this->paymentIdempotencyKey,
                    auth()->user(),
                    $this->manualReference,
                    $this->confirmationNote,
                    AuditContext::fromRequest(request()),
                );

            $this->completedTransaction = PosReceiptFormatter::format(
                $result['transaction'],
                $result['payment'],
                $this->cart,
                auth()->user()->name,
                $this->paymentMethod === 'cash' ? $result['cash_received'] : null,
                $this->paymentMethod === 'cash' ? $result['change'] : null,
            );
            $this->showPaymentModal = false;
            $this->showReceipt = true;
            if (($result['requires_refund'] ?? false) === true) {
                $this->setFeedback('Dana tercatat diterima, tetapi stok gagal. Refund wajib diproses; jangan bayar ulang.', 'error');
            }
        } catch (ValidationException $exception) {
            $this->setFeedback((string) collect($exception->errors())->flatten()->first(), 'error');
        } catch (ApiProblemException $exception) {
            $this->setFeedback($exception->getMessage(), 'error');
            if ($exception->errorCode === 'INSUFFICIENT_STOCK') {
                $this->renewAttempt();
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->setFeedback('Pembayaran gagal. Silakan coba kembali.', 'error');
        } finally {
            $this->processingPayment = false;
        }
    }

    public function newTransaction(): void
    {
        $this->resetCart();
        $this->showReceipt = false;
        $this->completedTransaction = null;
        $this->feedback = '';
    }

    private function recalculateLine(int $index): void
    {
        $gross = (float) $this->cart[$index]['harga_jual'] * $this->cart[$index]['qty'];
        $discount = min($gross, (float) $this->cart[$index]['discount_amount']);
        $this->cart[$index]['discount_amount'] = number_format($discount, 2, '.', '');
        $this->cart[$index]['subtotal'] = number_format($gross - $discount, 2, '.', '');
    }

    private function renewAttempt(): void
    {
        $this->idempotencyKey = (string) Str::uuid();
        $this->paymentIdempotencyKey = (string) Str::uuid();
    }

    private function resetCart(): void
    {
        $this->cart = [];
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->showPaymentModal = false;
        $this->cashReceived = '0';
        $this->change = '0';
        $this->paymentMethod = 'cash';
        $this->manualReference = '';
        $this->confirmationNote = '';
        $this->manualConfirmed = false;
        $this->selectedCartIndex = null;
        $this->processingPayment = false;
        $this->renewAttempt();
    }

    private function setFeedback(string $message, string $type = 'success'): void
    {
        $this->feedback = $message;
        $this->feedbackType = $type;
    }

    public function render()
    {
        return view('livewire.pos-screen')->layout('layouts.app', ['title' => 'POS - Kasir']);
    }
}
