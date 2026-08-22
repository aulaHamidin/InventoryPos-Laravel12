<?php

namespace App\Models;

use App\Enums\BillingPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPayment extends Model
{
    protected $fillable = [
        'tenant_id', 'subscription_id', 'invoice_id', 'amount', 'status',
        'provider', 'provider_reference', 'recorded_by_admin_id',
        'verified_by_admin_id', 'verified_at', 'failure_reason',
    ];

    protected $hidden = ['provider_reference'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => BillingPaymentStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by_admin_id');
    }
}
