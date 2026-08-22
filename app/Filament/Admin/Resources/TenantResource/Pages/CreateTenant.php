<?php

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Actions\Platform\CreateTenantAction;
use App\Filament\Admin\Resources\TenantResource;
use App\Models\Plan;
use App\Support\AuditContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $result = app(CreateTenantAction::class)->execute(
            auth('admin')->user(), $data['nama_toko'], $data['owner_name'], $data['email'], $data['no_hp'],
            $data['password'], Plan::query()->findOrFail($data['plan_id']), (bool) $data['trial'], AuditContext::fromRequest(request()),
        );

        return $result['tenant'];
    }
}
