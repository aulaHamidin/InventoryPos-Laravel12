<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemSupplier extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'item_id', 'supplier_id', 'supplier_sku', 'harga_beli_terakhir',
        'lead_time_days', 'is_preferred',
    ];

    protected function casts(): array
    {
        return ['harga_beli_terakhir' => 'decimal:2', 'lead_time_days' => 'integer', 'is_preferred' => 'boolean'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
