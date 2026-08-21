<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Events\ItemAnalyticsRecalculationRequested;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\Decimal;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertItemSupplierAction
{
    public function __construct(
        private readonly RecordAuditAction $audit,
        private readonly SetPreferredSupplierAction $setPreferred,
        private readonly UnsetPreferredSupplierAction $unsetPreferred,
    ) {}

    public function execute(int $itemId, int $supplierId, array $data, User $actor, ?AuditContext $context = null): ItemSupplier
    {
        OwnerActorGuard::assert($actor);
        OwnershipGuard::validate(Item::class, $itemId);
        OwnershipGuard::validate(Supplier::class, $supplierId);

        $price = null;
        if (array_key_exists('harga_beli_terakhir', $data) && $data['harga_beli_terakhir'] !== null) {
            try {
                $price = Decimal::money($data['harga_beli_terakhir']);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['harga_beli_terakhir' => ['Harga tidak valid.']]);
            }
        }

        [$link, $preferredLeadTimeChanged] = DB::transaction(function () use ($itemId, $supplierId, $data, $price, $actor, $context): array {
            Item::whereKey($itemId)->lockForUpdate()->firstOrFail();

            $link = ItemSupplier::where('item_id', $itemId)
                ->where('supplier_id', $supplierId)
                ->lockForUpdate()
                ->first();
            $wasPreferred = (bool) ($link?->is_preferred ?? false);
            $oldLeadTime = $link?->lead_time_days;
            $link ??= new ItemSupplier(['item_id' => $itemId, 'supplier_id' => $supplierId]);
            $link->fill([
                'supplier_sku' => $data['supplier_sku'] ?? null,
                'harga_beli_terakhir' => $price,
                'lead_time_days' => $data['lead_time_days'] ?? null,
            ])->save();

            $this->audit->execute('item_supplier.upserted', $actor, $link, newValues: $link->toArray(), context: $context);

            return [
                $link,
                $wasPreferred && (string) $oldLeadTime !== (string) $link->lead_time_days,
            ];
        });

        if (! empty($data['is_preferred'])) {
            $preferred = $this->setPreferred->execute($link->getKey(), $actor, $context);
            if ($preferredLeadTimeChanged) {
                ItemAnalyticsRecalculationRequested::dispatch(
                    TenantContext::id(),
                    [$itemId],
                    'preferred_supplier_lead_time_changed',
                );
            }

            return $preferred;
        }
        if (array_key_exists('is_preferred', $data) && ! $data['is_preferred'] && $link->is_preferred) {
            return $this->unsetPreferred->execute($link->getKey(), $actor, $context);
        }
        if ($preferredLeadTimeChanged) {
            ItemAnalyticsRecalculationRequested::dispatch(
                TenantContext::id(),
                [$itemId],
                'preferred_supplier_lead_time_changed',
            );
        }

        return $link->fresh(['item', 'supplier']);
    }
}
