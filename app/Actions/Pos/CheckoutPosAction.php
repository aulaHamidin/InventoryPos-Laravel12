<?php

namespace App\Actions\Pos;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\PosTransactionStatus;
use App\Exceptions\ApiProblemException;
use App\Models\Item;
use App\Models\PosTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\Decimal;
use App\Support\OwnershipGuard;
use App\Support\PosActorGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutPosAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(array $items, string $idempotencyKey, User $actor, ?AuditContext $context = null): PosTransaction
    {
        PosActorGuard::assertOperator($actor);

        if ($items === [] || trim($idempotencyKey) === '') {
            throw ValidationException::withMessages([
                'items' => ['Keranjang tidak boleh kosong.'],
                'idempotency_key' => ['Idempotency key wajib diisi.'],
            ]);
        }

        $merged = [];
        foreach ($items as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $qty = (int) ($line['qty'] ?? 0);
            if ($itemId <= 0 || $qty <= 0) {
                throw ValidationException::withMessages(['items' => ['Setiap item_id dan qty harus valid.']]);
            }

            try {
                $discount = Decimal::money($line['discount_amount'] ?? 0);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['items' => ['Discount amount tidak valid.']]);
            }

            if (! isset($merged[$itemId])) {
                $merged[$itemId] = ['item_id' => $itemId, 'qty' => 0, 'discount_amount' => '0.00'];
            }
            $merged[$itemId]['qty'] += $qty;
            $merged[$itemId]['discount_amount'] = Decimal::add($merged[$itemId]['discount_amount'], $discount);
        }

        ksort($merged, SORT_NUMERIC);
        $canonicalItems = array_values($merged);
        $requestHash = hash('sha256', json_encode($canonicalItems, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($canonicalItems, $idempotencyKey, $requestHash, $actor, $context): PosTransaction {
            $tenantId = TenantContext::id();
            $tenant = Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();

            $existing = PosTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ((int) $existing->cashier_id !== (int) $actor->getKey()
                    || ! hash_equals($existing->request_hash, $requestHash)) {
                    throw new ApiProblemException(
                        'Idempotency key telah digunakan untuk payload berbeda.',
                        'IDEMPOTENCY_CONFLICT',
                        409,
                    );
                }

                return $existing->load('items');
            }

            $itemIds = array_column($canonicalItems, 'item_id');
            OwnershipGuard::forTenantMany($tenant, Item::class, $itemIds);

            $lockedItems = Item::whereIn('id', $itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $grossTotal = '0.00';
            $discountTotal = '0.00';
            $lines = [];

            foreach ($canonicalItems as $line) {
                $item = $lockedItems->get($line['item_id']);
                if ($item === null || ! $item->is_active) {
                    throw ValidationException::withMessages(['items' => ["Item {$line['item_id']} tidak tersedia."]]);
                }

                if (! $tenant->allow_negative_stock && $item->stok_saat_ini < $line['qty']) {
                    throw new ApiProblemException("Stok {$item->nama} tidak mencukupi.", 'INSUFFICIENT_STOCK', 422);
                }

                $price = Decimal::money($item->harga_jual);
                $gross = Decimal::mul($price, $line['qty']);
                if (Decimal::compare($line['discount_amount'], $gross) > 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Diskon {$item->nama} tidak boleh melebihi nilai bruto baris."],
                    ]);
                }

                $grossTotal = Decimal::add($grossTotal, $gross);
                $discountTotal = Decimal::add($discountTotal, $line['discount_amount']);
                $lines[] = [
                    'item_id' => $item->getKey(),
                    'qty' => $line['qty'],
                    'returned_qty' => 0,
                    'harga_saat_transaksi' => $price,
                    'discount_amount' => $line['discount_amount'],
                    'subtotal_amount' => $gross,
                ];
            }

            $netTotal = Decimal::sub($grossTotal, $discountTotal);
            if (Decimal::compare($netTotal, '0.00') <= 0) {
                throw ValidationException::withMessages([
                    'items' => ['Total transaksi setelah diskon harus lebih dari 0.'],
                ]);
            }

            $transaction = PosTransaction::create([
                'cashier_id' => $actor->getKey(),
                'invoice_number' => 'POS-'.$tenantId.'-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()),
                'status' => PosTransactionStatus::PendingPayment,
                'subtotal_amount' => $grossTotal,
                'discount_amount' => $discountTotal,
                'total_amount' => $netTotal,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
            ]);

            foreach ($lines as $line) {
                $transaction->items()->create($line);
            }

            $this->audit->execute(
                'pos.checkout',
                $actor,
                $transaction,
                newValues: [
                    'invoice_number' => $transaction->invoice_number,
                    'subtotal_amount' => $grossTotal,
                    'discount_amount' => $discountTotal,
                    'total_amount' => $transaction->total_amount,
                ],
                context: $context,
            );

            return $transaction->load('items');
        }, 3);
    }
}
