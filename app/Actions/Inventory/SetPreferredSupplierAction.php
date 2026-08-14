<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;

class SetPreferredSupplierAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $itemSupplierId, User $actor, ?AuditContext $context = null): ItemSupplier
    {
        OwnershipGuard::validate(User::class, $actor->getKey());
        $link = OwnershipGuard::validate(ItemSupplier::class, $itemSupplierId);
        OwnershipGuard::validate(Item::class, $link->item_id);

        $itemId = (int) $link->item_id;

        return DB::transaction(function () use ($itemSupplierId, $itemId, $actor, $context): ItemSupplier {
            Item::whereKey($itemId)->lockForUpdate()->firstOrFail();
            $link = ItemSupplier::whereKey($itemSupplierId)->lockForUpdate()->firstOrFail();

            ItemSupplier::where('item_id', $link->item_id)->update(['is_preferred' => false]);
            $link->is_preferred = true;
            $link->save();

            $this->audit->execute('item.preferred_supplier_changed', $actor, $link, newValues: ['is_preferred' => true], context: $context);

            return $link->fresh(['item', 'supplier']);
        });
    }
}
