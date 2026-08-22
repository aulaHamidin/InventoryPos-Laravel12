<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\SubscriptionCapability;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\Decimal;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockInAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(
        int $itemId,
        int $qty,
        int|float|string $hargaSatuan,
        User $actor,
        ?int $supplierId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
        ?AuditContext $context = null,
    ): StockMovement {
        if ($qty <= 0) {
            throw ValidationException::withMessages(['qty' => ['Kuantitas harus lebih dari 0.']]);
        }

        try {
            $price = Decimal::money($hargaSatuan);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['harga_satuan' => ['Harga satuan tidak valid.']]);
        }

        OwnerActorGuard::assert($actor, SubscriptionCapability::Operate);
        OwnershipGuard::validate(Item::class, $itemId);
        if ($supplierId !== null) {
            OwnershipGuard::validate(Supplier::class, $supplierId);
        }

        return DB::transaction(function () use ($itemId, $qty, $price, $actor, $supplierId, $referenceType, $referenceId, $note, $context): StockMovement {
            $item = Item::whereKey($itemId)->lockForUpdate()->firstOrFail();
            $oldStock = $item->stok_saat_ini;
            $newStock = $oldStock + $qty;

            $average = $oldStock <= 0
                ? $price
                : Decimal::div(
                    Decimal::add(Decimal::mul((string) $item->average_cost, $oldStock), Decimal::mul($price, $qty)),
                    $newStock,
                );

            $item->update(['stok_saat_ini' => $newStock, 'harga_beli' => $price, 'average_cost' => $average]);

            $movement = StockMovement::create([
                'item_id' => $itemId,
                'user_id' => $actor->getKey(),
                'supplier_id' => $supplierId,
                'movement_type' => 'stock_in',
                'qty' => $qty,
                'direction' => 'in',
                'harga_satuan' => $price,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
            ]);

            $this->audit->execute('stock.in', $actor, $movement, newValues: $movement->toArray(), context: $context);

            return $movement;
        });
    }
}
