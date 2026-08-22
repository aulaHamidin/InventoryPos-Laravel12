<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class SubscriptionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'subscription_id', 'event_type', 'from_status', 'to_status',
        'actor_type', 'actor_id', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => SubscriptionStatus::class,
            'to_status' => SubscriptionStatus::class,
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException('Subscription events are immutable.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('Subscription events are immutable.');
    }
}
