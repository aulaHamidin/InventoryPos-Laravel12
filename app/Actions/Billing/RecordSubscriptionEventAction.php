<?php

namespace App\Actions\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Admin;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Support\SensitiveDataRedactor;

final class RecordSubscriptionEventAction
{
    public function __construct(private readonly SensitiveDataRedactor $redactor) {}

    public function execute(
        Subscription $subscription,
        string $eventType,
        ?SubscriptionStatus $from,
        ?SubscriptionStatus $to,
        User|Admin|null $actor = null,
        array $metadata = [],
    ): SubscriptionEvent {
        return SubscriptionEvent::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->getKey(),
            'event_type' => $eventType,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'actor_type' => match (true) {
                $actor instanceof Admin => 'admin',
                $actor instanceof User => 'user',
                default => 'system',
            },
            'actor_id' => $actor?->getKey(),
            'metadata' => $this->redactor->redact($metadata),
        ]);
    }
}
