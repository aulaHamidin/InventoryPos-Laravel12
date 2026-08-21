<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Events\ItemAnalyticsRecalculationRequested;
use App\Models\Category;
use App\Models\Item;
use App\Models\Rack;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        unset(
            $data['tenant_id'],
            $data['stok_saat_ini'],
            $data['average_cost'],
            $data['kode'],
            $data['movement_class'],
            $data['analytics_calculated_at'],
        );
        foreach (['stok_minimal', 'lead_time_days', 'safety_stock_days'] as $field) {
            if (array_key_exists($field, $data) && (! is_numeric($data[$field]) || (int) $data[$field] < 0)) {
                throw ValidationException::withMessages([$field => ['Nilai harus berupa integer non-negatif.']]);
            }
        }
        if (array_key_exists('threshold_mode', $data)
            && ! in_array($data['threshold_mode'], ['manual', 'auto_velocity'], true)) {
            throw ValidationException::withMessages([
                'threshold_mode' => ['Mode threshold tidak valid.'],
            ]);
        }

        return DB::transaction(function () use ($itemId, $data, $actor, $context): Item {
            $item = Item::whereKey($itemId)->lockForUpdate()->firstOrFail();
            if ($item->threshold_mode !== 'auto_velocity'
                && ($data['threshold_mode'] ?? null) === 'auto_velocity') {
                throw ValidationException::withMessages([
                    'threshold_mode' => ['Mode otomatis hanya dapat diaktifkan melalui Smart Threshold.'],
                ]);
            }
            if (array_key_exists('stok_minimal', $data)
                && $item->threshold_mode === 'auto_velocity'
                && (int) $data['stok_minimal'] !== (int) $item->stok_minimal
                && ($data['threshold_mode'] ?? null) !== 'manual') {
                throw ValidationException::withMessages([
                    'stok_minimal' => ['Pilih mode manual untuk mengubah stok minimal item otomatis.'],
                ]);
            }

            $old = $item->toArray();
            $item->update($data);
            $fresh = $item->fresh();
            $this->audit->execute('item.updated', $actor, $item, oldValues: $old, newValues: $fresh->toArray(), context: $context);

            $analyticsFields = ['lead_time_days', 'safety_stock_days', 'threshold_mode'];
            $analyticsChanged = collect($analyticsFields)->contains(
                fn (string $field): bool => array_key_exists($field, $data)
                    && (string) ($old[$field] ?? '') !== (string) ($fresh->{$field} ?? ''),
            );
            if ($analyticsChanged) {
                ItemAnalyticsRecalculationRequested::dispatch(
                    TenantContext::id(),
                    [(int) $item->getKey()],
                    'item_configuration_changed',
                );
            }

            return $fresh;
        });
    }
}
