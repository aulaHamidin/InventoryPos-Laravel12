<?php

namespace App\Actions\Opname;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\StockOpnameStatus;
use App\Enums\SubscriptionCapability;
use App\Exceptions\ApiProblemException;
use App\Models\Item;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveOpnameCountAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $opnameId, array $countedItems, User $actor, ?AuditContext $context = null): Collection
    {
        if ($countedItems === []) {
            throw ValidationException::withMessages(['items' => ['Minimal satu item wajib dikirim.']]);
        }

        $payload = collect($countedItems);
        $itemIds = $payload->map(fn (array $row): int => (int) ($row['item_id'] ?? 0));
        if ($itemIds->contains(fn (int $id): bool => $id <= 0) || $itemIds->unique()->count() !== $itemIds->count()) {
            throw ValidationException::withMessages(['items' => ['Item ID wajib valid dan tidak boleh duplikat.']]);
        }
        foreach ($payload as $index => $row) {
            if (! array_key_exists('qty_fisik', $row) || filter_var($row['qty_fisik'], FILTER_VALIDATE_INT) === false || (int) $row['qty_fisik'] < 0) {
                throw ValidationException::withMessages(["items.{$index}.qty_fisik" => ['Qty fisik wajib berupa integer non-negatif.']]);
            }
            if (isset($row['note']) && mb_strlen((string) $row['note']) > 1000) {
                throw ValidationException::withMessages(["items.{$index}.note" => ['Catatan maksimal 1000 karakter.']]);
            }
        }

        OwnerActorGuard::assert($actor, SubscriptionCapability::Operate);
        OwnershipGuard::validate(StockOpname::class, $opnameId);
        $itemIds->each(fn (int $itemId) => OwnershipGuard::validate(Item::class, $itemId));

        return DB::transaction(function () use ($opnameId, $payload, $itemIds, $actor, $context): Collection {
            $opname = StockOpname::whereKey($opnameId)->lockForUpdate()->firstOrFail();
            if ($opname->status !== StockOpnameStatus::Draft) {
                throw new ApiProblemException('Opname yang sudah selesai tidak dapat diubah.', 'INVALID_STATE_TRANSITION', 409);
            }

            $orderedIds = $itemIds->unique()->sort()->values();
            $details = StockOpnameDetail::where('stock_opname_id', $opnameId)
                ->whereIn('item_id', $orderedIds)
                ->orderBy('item_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('item_id');
            if ($details->count() !== $orderedIds->count()) {
                throw ValidationException::withMessages(['items' => ['Satu atau lebih item bukan anggota sesi opname.']]);
            }

            $items = Item::withTrashed()->whereIn('id', $orderedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($items->count() !== $orderedIds->count()) {
                throw ValidationException::withMessages(['items' => ['Satu atau lebih item tidak tersedia.']]);
            }

            $rows = $payload->keyBy(fn (array $row): int => (int) $row['item_id']);
            $changedIds = [];
            foreach ($orderedIds as $itemId) {
                $detail = $details->get($itemId);
                $row = $rows->get($itemId);
                $values = [
                    'qty_fisik' => (int) $row['qty_fisik'],
                    'note' => isset($row['note']) && trim((string) $row['note']) !== '' ? trim((string) $row['note']) : null,
                ];
                if ($detail->counted_at === null) {
                    $values['qty_sistem_at_count'] = $items->get($itemId)->stok_saat_ini;
                    $values['counted_at'] = now();
                }
                if ($detail->fill($values)->isDirty()) {
                    $detail->save();
                    $changedIds[] = $itemId;
                }
            }

            if ($changedIds !== []) {
                $this->audit->execute(
                    'stock_opname.count_saved',
                    $actor,
                    $opname,
                    metadata: ['item_ids' => $changedIds, 'item_count' => count($changedIds)],
                    context: $context,
                );
            }

            return StockOpnameDetail::where('stock_opname_id', $opnameId)
                ->whereIn('item_id', $orderedIds)
                ->orderBy('item_id')
                ->get();
        }, 3);
    }
}
