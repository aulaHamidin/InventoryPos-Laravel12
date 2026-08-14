<?php

namespace App\Models;

use App\Enums\PosTransactionStatus;
use App\Models\Concerns\HasTenantScope;
use App\Models\Concerns\PreventsHistoricalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTransaction extends Model
{
    use HasTenantScope, PreventsHistoricalDeletion;

    protected $fillable = [
        'cashier_id', 'invoice_number', 'status', 'subtotal_amount',
        'discount_amount', 'total_amount', 'idempotency_key', 'request_hash', 'completed_at',
    ];

    protected $hidden = ['request_hash'];

    protected function casts(): array
    {
        return [
            'status' => PosTransactionStatus::class,
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosTransactionItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class);
    }
}
