<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Concerns\PreventsHistoricalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingListItem extends Model
{
    use HasTenantScope, PreventsHistoricalDeletion;

    protected $fillable = [
        'shopping_list_id', 'item_id', 'supplier_id', 'qty_disarankan',
        'qty_dibeli', 'qty_received', 'is_checked',
    ];

    protected function casts(): array
    {
        return [
            'qty_disarankan' => 'integer', 'qty_dibeli' => 'integer',
            'qty_received' => 'integer', 'is_checked' => 'boolean',
        ];
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
