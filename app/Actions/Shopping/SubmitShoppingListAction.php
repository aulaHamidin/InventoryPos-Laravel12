<?php

namespace App\Actions\Shopping;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\ShoppingListStatus;
use App\Enums\SubscriptionCapability;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitShoppingListAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $shoppingListId, array $submittedItems, User $actor, ?AuditContext $context = null): ShoppingList
    {
        OwnerActorGuard::assert($actor, SubscriptionCapability::Operate);
        OwnershipGuard::validate(ShoppingList::class, $shoppingListId);

        return DB::transaction(function () use ($shoppingListId, $submittedItems, $actor, $context): ShoppingList {
            $list = ShoppingList::whereKey($shoppingListId)->lockForUpdate()->firstOrFail();
            if ($list->status !== ShoppingListStatus::Draft) {
                throw ValidationException::withMessages(['status' => ['Hanya daftar draft yang dapat disubmit.']]);
            }

            $listItems = $list->items()->orderBy('id')->lockForUpdate()->get();
            if ($listItems->isEmpty() || count($submittedItems) !== $listItems->count()) {
                throw ValidationException::withMessages(['items' => ['Semua baris daftar belanja harus dikirim secara eksplisit.']]);
            }

            $payload = collect($submittedItems)->keyBy(fn (array $row): int => (int) ($row['shopping_list_item_id'] ?? 0));
            if ($payload->count() !== count($submittedItems)) {
                throw ValidationException::withMessages(['items' => ['ID baris daftar belanja tidak boleh duplikat.']]);
            }

            $selected = 0;
            foreach ($listItems as $listItem) {
                $row = $payload->get($listItem->getKey());
                if ($row === null) {
                    throw ValidationException::withMessages(['items' => ['Terdapat baris yang hilang dari payload.']]);
                }

                OwnershipGuard::validate(ShoppingListItem::class, $listItem->getKey());
                $checked = (bool) ($row['is_checked'] ?? false);
                $supplierId = isset($row['supplier_id']) ? (int) $row['supplier_id'] : null;
                $qty = isset($row['qty_dibeli']) ? (int) $row['qty_dibeli'] : null;

                if ($checked) {
                    $selected++;
                    if ($supplierId === null || $qty === null || $qty <= 0) {
                        throw ValidationException::withMessages(['items' => ['Setiap item terpilih wajib memiliki supplier dan qty_dibeli > 0.']]);
                    }
                    OwnershipGuard::validate(Supplier::class, $supplierId);
                }

                $listItem->update([
                    'is_checked' => $checked,
                    'supplier_id' => $checked ? $supplierId : null,
                    'qty_dibeli' => $checked ? $qty : null,
                ]);
            }

            if ($selected === 0) {
                throw ValidationException::withMessages(['items' => ['Pilih minimal satu item untuk dibeli.']]);
            }

            $list->update(['status' => ShoppingListStatus::Purchased, 'submitted_at' => now()]);
            $this->audit->execute('shopping_list.purchased', $actor, $list, newValues: ['selected_items' => $selected], context: $context);

            return $list->fresh(['items.item', 'items.supplier']);
        }, 3);
    }
}
