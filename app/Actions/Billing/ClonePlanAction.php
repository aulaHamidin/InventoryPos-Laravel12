<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Admin;
use App\Models\Plan;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

final class ClonePlanAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, Plan $source, string $newCode, ?string $name = null, ?string $price = null, ?AuditContext $context = null): Plan
    {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $source, $newCode, $name, $price, $context): Plan {
            $source = Plan::query()->lockForUpdate()->findOrFail($source->getKey());
            abort_if($source->is_internal, 403);
            $clone = Plan::query()->create([
                'code' => strtoupper(trim($newCode)),
                'name' => trim($name ?? $source->name),
                'billing_interval' => $source->billing_interval,
                'price' => Decimal::money($price ?? $source->price),
                'is_trial' => $source->is_trial,
                'trial_days' => $source->trial_days,
                'is_active' => true,
                'is_internal' => false,
            ]);
            $this->audit->execute('billing.plan_cloned', $actor, $clone, newValues: ['source_plan_id' => $source->getKey()], context: $context, global: true);

            return $clone;
        });
    }
}
