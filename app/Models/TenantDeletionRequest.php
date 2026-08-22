<?php

namespace App\Models;

use App\Enums\OperationalStatus;
use App\Enums\TenantDeletionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDeletionRequest extends Model
{
    protected $fillable = [
        'tenant_id', 'requested_by_user_id', 'reviewed_by_admin_id', 'status',
        'reason', 'review_reason', 'previous_operational_status', 'reviewed_at',
        'cancelled_at', 'purge_after', 'queued_at', 'purged_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantDeletionStatus::class,
            'previous_operational_status' => OperationalStatus::class,
            'reviewed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'purge_after' => 'immutable_datetime',
            'queued_at' => 'immutable_datetime',
            'purged_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }
}
