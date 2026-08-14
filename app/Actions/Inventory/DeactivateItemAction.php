<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Item;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;

class DeactivateItemAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $itemId, User $actor, ?AuditContext $context = null): Item
    {
        OwnershipGuard::validate(User::class, $actor->getKey());
        OwnershipGuard::validate(Item::class, $itemId);

        return DB::transaction(function () use ($itemId, $actor, $context): Item {
            $item = Item::whereKey($itemId)->lockForUpdate()->firstOrFail();
            $item->update(['is_active' => false]);
            $this->audit->execute('item.deactivated', $actor, $item, newValues: ['is_active' => false], context: $context);

            return $item;
        });
    }
}
