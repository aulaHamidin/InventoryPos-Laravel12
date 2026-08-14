<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;

class DeleteItemSupplierAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $itemSupplierId, User $actor, ?AuditContext $context = null): void
    {
        OwnershipGuard::validate(User::class, $actor->getKey());
        $guarded = OwnershipGuard::validate(ItemSupplier::class, $itemSupplierId);
        OwnershipGuard::validate(Item::class, $guarded->item_id);

        $itemId = (int) $guarded->item_id;

        DB::transaction(function () use ($itemSupplierId, $itemId, $actor, $context): void {
            Item::whereKey($itemId)->lockForUpdate()->firstOrFail();
            $link = ItemSupplier::whereKey($itemSupplierId)->lockForUpdate()->firstOrFail();
            $old = $link->toArray();
            $this->audit->execute('item_supplier.deleted', $actor, $link, oldValues: $old, context: $context);
            $link->delete();
        });
    }
}
