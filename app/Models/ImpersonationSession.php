<?php

namespace App\Models;

use App\Enums\ImpersonationEndReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationSession extends Model
{
    protected $fillable = [
        'admin_id', 'tenant_id', 'target_user_id', 'reason',
        'session_fingerprint_hash', 'started_at', 'expires_at', 'ended_at',
        'end_reason', 'metadata',
    ];

    protected $hidden = ['session_fingerprint_hash'];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'end_reason' => ImpersonationEndReason::class,
            'metadata' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
