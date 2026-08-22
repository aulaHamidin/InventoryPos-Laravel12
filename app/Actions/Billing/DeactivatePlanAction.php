<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Admin;
use App\Models\Plan;
use App\Support\AdminActorGuard;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;

final class DeactivatePlanAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(Admin $actor, Plan $plan, ?AuditContext $context = null): Plan
    {
        AdminActorGuard::superAdmin($actor);

        return DB::transaction(function () use ($actor, $plan, $context): Plan {
            $plan = Plan::query()->lockForUpdate()->findOrFail($plan->getKey());
            abort_if($plan->is_internal, 403);
            if (! $plan->is_active) {
                return $plan;
            }
            $plan->forceFill(['is_active' => false])->save();
            $this->audit->execute('billing.plan_deactivated', $actor, $plan, oldValues: ['is_active' => true], newValues: ['is_active' => false], context: $context, global: true);

            return $plan;
        });
    }
}
