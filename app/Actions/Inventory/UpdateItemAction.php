<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Category;
use App\Models\Item;
use App\Models\Rack;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;

class UpdateItemAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $itemId, array $data, User $actor, ?AuditContext $context = null): Item
    {
        OwnershipGuard::validate(User::class, $actor->getKey());
        OwnershipGuard::validate(Item::class, $itemId);
        if (isset($data['category_id'])) {
            OwnershipGuard::validate(Category::class, (int) $data['category_id']);
        }
        if (isset($data['rack_id'])) {
            OwnershipGuard::validate(Rack::class, (int) $data['rack_id']);
        }

        unset($data['tenant_id'], $data['stok_saat_ini'], $data['average_cost'], $data['kode']);

        return DB::transaction(function () use ($itemId, $data, $actor, $context): Item {
            $item = Item::whereKey($itemId)->lockForUpdate()->firstOrFail();
            $old = $item->toArray();
            $item->update($data);
            $this->audit->execute('item.updated', $actor, $item, oldValues: $old, newValues: $item->fresh()->toArray(), context: $context);

            return $item;
        });
    }
}
