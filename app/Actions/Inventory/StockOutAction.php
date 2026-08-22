<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\SubscriptionCapability;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOutAction
{
    private const TYPES = ['stock_out', 'sale', 'supplier_return', 'damaged'];

    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(
        int $itemId,
        int $qty,
        User $actor,
        string $movementType = 'stock_out',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
        ?AuditContext $context = null,
    ): StockMovement {
        if ($qty <= 0) {
            throw ValidationException::withMessages(['qty' => ['Kuantitas harus lebih dari 0.']]);
        }
        if (! in_array($movementType, self::TYPES, true)) {
            throw ValidationException::withMessages(['movement_type' => ['Tipe pergerakan tidak valid.']]);
        }

        OwnerActorGuard::assert($actor, SubscriptionCapability::Operate);
        OwnershipGuard::validate(Item::class, $itemId);

        return DB::transaction(function () use ($itemId, $qty, $actor, $movementType, $referenceType, $referenceId, $note, $context): StockMovement {
            $item = Item::whereKey($itemId)->lockForUpdate()->firstOrFail();
            $newStock = $item->stok_saat_ini - $qty;

            if ($newStock < 0 && ! TenantContext::get()->allow_negative_stock) {
                throw ValidationException::withMessages(['qty' => ['Stok tidak mencukupi dan stok negatif tidak diizinkan.']]);
            }

            $item->update(['stok_saat_ini' => $newStock]);

            $movement = StockMovement::create([
                'item_id' => $itemId,
                'user_id' => $actor->getKey(),
                'movement_type' => $movementType,
                'qty' => $qty,
                'direction' => 'out',
                'harga_satuan' => $item->average_cost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
            ]);

            $this->audit->execute('stock.out', $actor, $movement, newValues: $movement->toArray(), context: $context);

            return $movement;
        });
    }
}
