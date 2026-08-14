<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasTenantScope;

    protected $fillable = ['nama', 'kontak', 'alamat'];

    public function itemSupplierLinks(): HasMany
    {
        return $this->hasMany(ItemSupplier::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_suppliers')
            ->withPivot(['id', 'tenant_id', 'supplier_sku', 'harga_beli_terakhir', 'lead_time_days', 'is_preferred'])
            ->withTimestamps();
    }
}
