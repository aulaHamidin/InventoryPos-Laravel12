<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustStockAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(
        int $itemId,
        int $qty,
        string $direction,
        string $note,
        User $actor,
        ?AuditContext $context = null,
    ): StockMovement {
        if ($qty <= 0 || ! in_array($direction, ['in', 'out'], true)) {
            throw ValidationException::withMessages(['qty' => ['Qty harus positif dan direction harus in atau out.']]);
        }
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => ['Catatan penyesuaian wajib diisi.']]);
        }

        OwnershipGuard::validate(User::class, $actor->getKey());
        OwnershipGuard::validate(Item::class, $itemId);

        return DB::transaction(function () use ($itemId, $qty, $direction, $note, $actor, $context): StockMovement {
            $item = Item::whereKey($itemId)->lockForUpdate()->firstOrFail();
            $before = $item->stok_saat_ini;
            $after = $direction === 'in' ? $before + $qty : $before - $qty;

            if ($after < 0 && ! TenantContext::get()->allow_negative_stock) {
                throw ValidationException::withMessages(['qty' => ['Penyesuaian akan membuat stok negatif.']]);
            }

            $item->update(['stok_saat_ini' => $after]);

            $movement = StockMovement::create([
                'item_id' => $itemId,
                'user_id' => $actor->getKey(),
                'movement_type' => 'adjustment',
                'qty' => $qty,
                'direction' => $direction,
                'harga_satuan' => $item->average_cost,
                'note' => $note,
            ]);

            $this->audit->execute(
                'stock.adjusted',
                $actor,
                $movement,
                oldValues: ['stok_saat_ini' => $before],
                newValues: ['stok_saat_ini' => $after],
                context: $context,
            );

            return $movement;
        });
    }
}
