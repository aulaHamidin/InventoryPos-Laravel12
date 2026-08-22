<?php

namespace App\Filament\Admin\Resources\PlanResource\Pages;

use App\Actions\Billing\CreatePlanAction;
use App\Enums\BillingInterval;
use App\Filament\Admin\Resources\PlanResource;
use App\Support\AuditContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatePlanAction::class)->execute(
            auth('admin')->user(), $data['code'], $data['name'], BillingInterval::from($data['billing_interval']),
            (string) $data['price'], (bool) ($data['is_trial'] ?? false), isset($data['trial_days']) ? (int) $data['trial_days'] : null,
            AuditContext::fromRequest(request()),
        );
    }
}
