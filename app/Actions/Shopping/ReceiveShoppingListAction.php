<?php

namespace App\Actions\Shopping;

use App\Actions\Audit\RecordAuditAction;
use App\Actions\Inventory\StockInAction;
use App\Enums\ShoppingListStatus;
use App\Models\Item;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiveShoppingListAction
{
    public function __construct(
        private readonly StockInAction $stockIn,
        private readonly RecordAuditAction $audit,
    ) {}

    public function execute(int $shoppingListId, array $receivedItems, User $actor, ?AuditContext $context = null): ShoppingList
    {
        OwnerActorGuard::assert($actor);
        OwnershipGuard::validate(ShoppingList::class, $shoppingListId);

        return DB::transaction(function () use ($shoppingListId, $receivedItems, $actor, $context): ShoppingList {
            $list = ShoppingList::whereKey($shoppingListId)->lockForUpdate()->firstOrFail();
            if ($list->status !== ShoppingListStatus::Purchased) {
                throw ValidationException::withMessages(['status' => ['Daftar belanja sudah diterima atau belum disubmit.']]);
            }

            $listItems = $list->items()->where('is_checked', true)->orderBy('id')->lockForUpdate()->get();
            $payload = collect($receivedItems)->keyBy(fn (array $row): int => (int) ($row['shopping_list_item_id'] ?? 0));

            if ($listItems->isEmpty() || $payload->count() !== $listItems->count() || count($receivedItems) !== $payload->count()) {
                throw ValidationException::withMessages(['items' => ['Kuantitas aktual wajib dikirim tepat satu kali untuk seluruh item yang dibeli.']]);
            }

            $lockedStockItems = Item::whereIn('id', $listItems->pluck('item_id')->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($listItems->sortBy('item_id') as $listItem) {
                $row = $payload->get($listItem->getKey());
                $qty = (int) ($row['qty_received'] ?? 0);
                if ($row === null || $qty <= 0 || ! array_key_exists('harga_satuan', $row)) {
                    throw ValidationException::withMessages(['items' => ['Setiap item yang dibeli wajib memiliki qty_received dan harga_satuan.']]);
                }
                if (! $lockedStockItems->has($listItem->item_id)) {
                    throw ValidationException::withMessages(['items' => ['Item stok tidak tersedia.']]);
                }

                OwnershipGuard::validate(ShoppingListItem::class, $listItem->getKey());

                $this->stockIn->execute(
                    itemId: $listItem->item_id,
                    qty: $qty,
                    hargaSatuan: $row['harga_satuan'],
                    actor: $actor,
                    supplierId: $listItem->supplier_id,
                    referenceType: ShoppingList::class,
                    referenceId: $list->getKey(),
                    note: "Penerimaan daftar belanja #{$list->getKey()}",
                    context: $context,
                );

                $listItem->update(['qty_received' => $qty]);
            }

            $list->update(['status' => ShoppingListStatus::Completed, 'completed_at' => now()]);
            $this->audit->execute('shopping_list.completed', $actor, $list, newValues: ['completed_at' => $list->completed_at], context: $context);

            return $list->fresh(['items.item', 'items.supplier']);
        }, 3);
    }
}
