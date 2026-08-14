<?php

namespace App\Models;

use App\Enums\PosPaymentMethod;
use App\Enums\PosPaymentStatus;
use App\Models\Concerns\HasTenantScope;
use App\Models\Concerns\PreventsHistoricalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPayment extends Model
{
    use HasTenantScope, PreventsHistoricalDeletion;

    protected $fillable = [
        'pos_transaction_id', 'method', 'amount', 'status', 'gateway_reference',
        'refunded_amount', 'refunded_at', 'refunded_by', 'idempotency_key', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => PosPaymentMethod::class, 'status' => PosPaymentStatus::class,
            'amount' => 'decimal:2', 'refunded_amount' => 'decimal:2',
            'refunded_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class, 'pos_transaction_id');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by')->withTrashed();
    }
}
