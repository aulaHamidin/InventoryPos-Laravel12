<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Concerns\PreventsHistoricalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosTransactionItem extends Model
{
    use HasTenantScope, PreventsHistoricalDeletion;

    public const UPDATED_AT = null;

    protected $fillable = [
        'pos_transaction_id', 'item_id', 'qty', 'returned_qty',
        'harga_saat_transaksi', 'discount_amount', 'subtotal_amount',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer', 'returned_qty' => 'integer',
            'harga_saat_transaksi' => 'decimal:2', 'discount_amount' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class, 'pos_transaction_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }
}
