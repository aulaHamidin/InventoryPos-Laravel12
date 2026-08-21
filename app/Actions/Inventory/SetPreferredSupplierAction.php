<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Events\ItemAnalyticsRecalculationRequested;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;

class SetPreferredSupplierAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $itemSupplierId, User $actor, ?AuditContext $context = null): ItemSupplier
    {
        OwnerActorGuard::assert($actor);
        $link = OwnershipGuard::validate(ItemSupplier::class, $itemSupplierId);
        OwnershipGuard::validate(Item::class, $link->item_id);

        $itemId = (int) $link->item_id;

        return DB::transaction(function () use ($itemSupplierId, $itemId, $actor, $context): ItemSupplier {
            Item::whereKey($itemId)->lockForUpdate()->firstOrFail();
            $link = ItemSupplier::whereKey($itemSupplierId)->lockForUpdate()->firstOrFail();
            $currentPreferredId = ItemSupplier::where('item_id', $link->item_id)
                ->where('is_preferred', true)
                ->value('id');
            if ((int) $currentPreferredId === (int) $link->getKey()) {
                return $link->fresh(['item', 'supplier']);
            }

            ItemSupplier::where('item_id', $link->item_id)->update(['is_preferred' => false]);
            $link->is_preferred = true;
            $link->save();

            $this->audit->execute(
                'item.preferred_supplier_changed',
                $actor,
                $link,
                oldValues: ['preferred_item_supplier_id' => $currentPreferredId],
                newValues: ['preferred_item_supplier_id' => $link->getKey()],
                context: $context,
            );
            ItemAnalyticsRecalculationRequested::dispatch(
                TenantContext::id(),
                [$itemId],
                'preferred_supplier_set',
            );

            return $link->fresh(['item', 'supplier']);
        });
    }
}
