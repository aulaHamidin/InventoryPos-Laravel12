<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\SubscriptionStatus;
use App\Models\Admin;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TransitionSubscriptionAction
{
    private const LEGAL = [
        'trial' => ['active', 'expired'],
        'active' => ['past_due'],
        'past_due' => ['active', 'suspended'],
        'suspended' => ['active'],
        'expired' => [],
    ];

    public function __construct(private readonly RecordSubscriptionEventAction $events, private readonly RecordAuditAction $audit) {}

    public function execute(
        Subscription $subscription,
        SubscriptionStatus $to,
        User|Admin|null $actor = null,
        ?AuditContext $context = null,
        ?SubscriptionStatus $expectedFrom = null,
    ): Subscription {
        return DB::transaction(function () use ($subscription, $to, $actor, $context, $expectedFrom): Subscription {
            Tenant::query()->lockForUpdate()->findOrFail($subscription->tenant_id);
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $from = $subscription->status;
            if ($expectedFrom !== null && $from !== $expectedFrom) {
                return $subscription;
            }
            if ($from === $to) {
                return $subscription;
            }
            if (! in_array($to->value, self::LEGAL[$from->value], true)) {
                throw new ConflictHttpException("Illegal subscription transition: {$from->value} -> {$to->value}.");
            }
            $subscription->forceFill(['status' => $to])->save();
            $this->events->execute($subscription, 'transitioned', $from, $to, $actor);
            $this->audit->execute('billing.subscription_transitioned', $actor, $subscription, oldValues: ['status' => $from->value], newValues: ['status' => $to->value], context: $context, tenantId: $subscription->tenant_id);

            return $subscription;
        }, 3);
    }
}
