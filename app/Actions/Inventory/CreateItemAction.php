<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\MovementClass;
use App\Models\Category;
use App\Models\Item;
use App\Models\Rack;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateItemAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(array $data, User $actor, ?AuditContext $context = null): Item
    {
        OwnerActorGuard::assert($actor);
        $category = OwnershipGuard::validate(Category::class, (int) ($data['category_id'] ?? 0));
        $rack = isset($data['rack_id']) ? OwnershipGuard::validate(Rack::class, (int) $data['rack_id']) : null;

        if (isset($data['threshold_mode']) && $data['threshold_mode'] !== 'manual') {
            throw ValidationException::withMessages([
                'threshold_mode' => ['Item baru harus menggunakan threshold manual.'],
            ]);
        }

        foreach (['harga_beli', 'harga_jual', 'stok_minimal', 'lead_time_days', 'safety_stock_days'] as $field) {
            if (isset($data[$field]) && $data[$field] < 0) {
                throw ValidationException::withMessages([$field => ['Nilai tidak boleh negatif.']]);
            }
        }

        return DB::transaction(function () use ($data, $actor, $context, $category, $rack): Item {
            Tenant::whereKey(TenantContext::id())->lockForUpdate()->firstOrFail();

            $kode = trim((string) ($data['kode'] ?? ''));
            if ($kode === '') {
                $last = Item::withTrashed()
                    ->where('kode', 'like', $category->kode.'-%')
                    ->lockForUpdate()
                    ->orderByDesc('kode')
                    ->value('kode');
                $number = $last ? ((int) substr($last, strlen($category->kode) + 1)) + 1 : 1;
                $kode = $category->kode.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            }

            if (Item::withTrashed()->where('kode', $kode)->exists()) {
                throw ValidationException::withMessages(['kode' => ['Kode barang sudah digunakan.']]);
            }

            if (! empty($data['barcode']) && Item::withTrashed()->where('barcode', $data['barcode'])->exists()) {
                throw ValidationException::withMessages(['barcode' => ['Barcode sudah digunakan.']]);
            }

            $item = Item::create([
                'category_id' => $category->getKey(),
                'rack_id' => $rack?->getKey(),
                'kode' => $kode,
                'barcode' => $data['barcode'] ?? null,
                'nama' => $data['nama'],
                'satuan' => $data['satuan'] ?? 'Pcs',
                'harga_beli' => $data['harga_beli'] ?? 0,
                'average_cost' => 0,
                'harga_jual' => $data['harga_jual'] ?? 0,
                'stok_saat_ini' => 0,
                'stok_minimal' => $data['stok_minimal'] ?? 0,
                'threshold_mode' => 'manual',
                'lead_time_days' => $data['lead_time_days'] ?? 0,
                'safety_stock_days' => $data['safety_stock_days'] ?? 0,
                'exp_date' => $data['exp_date'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
            $item->movement_class = MovementClass::Unclassified;
            $item->save();

            $this->audit->execute('item.created', $actor, $item, newValues: $item->toArray(), context: $context);

            return $item;
        });
    }
}
