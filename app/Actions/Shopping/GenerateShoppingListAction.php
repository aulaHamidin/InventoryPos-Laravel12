<?php

namespace App\Actions\Shopping;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\ShoppingListStatus;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\ShoppingList;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use Illuminate\Support\Facades\DB;

class GenerateShoppingListAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(User $actor, ?AuditContext $context = null): ?ShoppingList
    {
        OwnerActorGuard::assert($actor);

        return DB::transaction(function () use ($actor, $context): ?ShoppingList {
            Tenant::whereKey(TenantContext::id())->lockForUpdate()->firstOrFail();

            $items = Item::where('is_active', true)
                ->whereColumn('stok_saat_ini', '<=', 'stok_minimal')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                return null;
            }

            $preferred = ItemSupplier::whereIn('item_id', $items->pluck('id'))
                ->where('is_preferred', true)
                ->orderBy('id')
                ->get()
                ->unique('item_id')
                ->keyBy('item_id');

            $list = ShoppingList::create([
                'created_by' => $actor->getKey(),
                'status' => ShoppingListStatus::Draft,
            ]);

            foreach ($items as $item) {
                $list->items()->create([
                    'item_id' => $item->getKey(),
                    'supplier_id' => $preferred->get($item->getKey())?->supplier_id,
                    'qty_disarankan' => max(1, $item->stok_minimal - $item->stok_saat_ini),
                    'qty_dibeli' => null,
                    'qty_received' => 0,
                    'is_checked' => true,
                ]);
            }

            $this->audit->execute('shopping_list.generated', $actor, $list, newValues: ['item_count' => $items->count()], context: $context);

            return $list->load(['items.item', 'items.supplier']);
        }, 3);
    }
}
