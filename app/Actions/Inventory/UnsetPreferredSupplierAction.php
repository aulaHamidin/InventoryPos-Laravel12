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

class UnsetPreferredSupplierAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $itemSupplierId, User $actor, ?AuditContext $context = null): ItemSupplier
    {
        OwnerActorGuard::assert($actor);
        $guarded = OwnershipGuard::validate(ItemSupplier::class, $itemSupplierId);
        OwnershipGuard::validate(Item::class, $guarded->item_id);

        return DB::transaction(function () use ($itemSupplierId, $actor, $context): ItemSupplier {
            $link = ItemSupplier::whereKey($itemSupplierId)->lockForUpdate()->firstOrFail();
            Item::whereKey($link->item_id)->lockForUpdate()->firstOrFail();
            if (! $link->is_preferred) {
                return $link;
            }

            $link->update(['is_preferred' => false]);
            $this->audit->execute(
                'item.preferred_supplier_cleared',
                $actor,
                $link,
                oldValues: ['is_preferred' => true],
                newValues: ['is_preferred' => false],
                context: $context,
            );
            ItemAnalyticsRecalculationRequested::dispatch(
                TenantContext::id(),
                [(int) $link->item_id],
                'preferred_supplier_unset',
            );

            return $link->fresh(['item', 'supplier']);
        });
    }
}
