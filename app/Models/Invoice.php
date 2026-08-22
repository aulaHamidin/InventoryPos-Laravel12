<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'tenant_id', 'subscription_id', 'target_plan_id', 'invoice_number',
        'amount', 'due_at', 'paid_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'due_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
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

    public function targetPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'target_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class);
    }
}
