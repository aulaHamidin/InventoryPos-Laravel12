<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\BillingInterval;
use App\Models\Admin;
use App\Models\Plan;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

final class CreatePlanAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(
        Admin $actor,
        string $code,
        string $name,
        BillingInterval $interval,
        string $price,
        bool $isTrial = false,
        ?int $trialDays = null,
        ?AuditContext $context = null,
    ): Plan {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $code, $name, $interval, $price, $isTrial, $trialDays, $context): Plan {
            $plan = Plan::query()->create([
                'code' => strtoupper(trim($code)),
                'name' => trim($name),
                'billing_interval' => $interval,
                'price' => Decimal::money($price),
                'is_trial' => $isTrial,
                'trial_days' => $isTrial ? ($trialDays ?? 14) : null,
                'is_active' => true,
                'is_internal' => false,
            ]);

            $this->audit->execute('billing.plan_created', $actor, $plan, newValues: $plan->only([
                'code', 'name', 'billing_interval', 'price', 'is_trial', 'trial_days', 'is_active',
            ]), context: $context, global: true);

            return $plan;
        });
    }
}
