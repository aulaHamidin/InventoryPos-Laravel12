<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasTenantScope, SoftDeletes;

    protected $fillable = [
        'category_id', 'rack_id', 'kode', 'barcode', 'nama', 'satuan',
        'harga_beli', 'average_cost', 'harga_jual', 'stok_saat_ini', 'stok_minimal',
        'threshold_mode', 'lead_time_days', 'safety_stock_days', 'exp_date',
        'movement_class', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'harga_beli' => 'decimal:2', 'average_cost' => 'decimal:2', 'harga_jual' => 'decimal:2',
            'stok_saat_ini' => 'integer', 'stok_minimal' => 'integer',
            'lead_time_days' => 'integer', 'safety_stock_days' => 'integer',
            'exp_date' => 'date', 'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function itemSupplierLinks(): HasMany
    {
        return $this->hasMany(ItemSupplier::class);
    }

    public function preferredSupplierLink(): HasOne
    {
        return $this->hasOne(ItemSupplier::class)->where('is_preferred', true);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'item_suppliers')
            ->withPivot(['id', 'tenant_id', 'supplier_sku', 'harga_beli_terakhir', 'lead_time_days', 'is_preferred'])
            ->withTimestamps();
    }
}
