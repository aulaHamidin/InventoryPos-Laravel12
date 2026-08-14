<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\Decimal;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertItemSupplierAction
{
    public function __construct(
        private readonly RecordAuditAction $audit,
        private readonly SetPreferredSupplierAction $setPreferred,
    ) {}

    public function execute(int $itemId, int $supplierId, array $data, User $actor, ?AuditContext $context = null): ItemSupplier
    {
        OwnershipGuard::validate(User::class, $actor->getKey());
        OwnershipGuard::validate(Item::class, $itemId);
        OwnershipGuard::validate(Supplier::class, $supplierId);

        $price = null;
        if (array_key_exists('harga_beli_terakhir', $data) && $data['harga_beli_terakhir'] !== null) {
            try {
                $price = Decimal::money($data['harga_beli_terakhir']);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['harga_beli_terakhir' => ['Harga tidak valid.']]);
            }
        }

        $link = DB::transaction(function () use ($itemId, $supplierId, $data, $price, $actor, $context): ItemSupplier {
            Item::whereKey($itemId)->lockForUpdate()->firstOrFail();

            $link = ItemSupplier::updateOrCreate(
                ['item_id' => $itemId, 'supplier_id' => $supplierId],
                [
                    'supplier_sku' => $data['supplier_sku'] ?? null,
                    'harga_beli_terakhir' => $price,
                    'lead_time_days' => $data['lead_time_days'] ?? null,
                ],
            );
            if (array_key_exists('is_preferred', $data) && ! $data['is_preferred']) {
                $link->update(['is_preferred' => false]);
            }

            $this->audit->execute('item_supplier.upserted', $actor, $link, newValues: $link->toArray(), context: $context);

            return $link;
        });

        return ! empty($data['is_preferred'])
            ? $this->setPreferred->execute($link->getKey(), $actor, $context)
            : $link->fresh(['item', 'supplier']);
    }
}
