<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Actions\Staff\CreateStaffAction;
use App\Filament\Resources\StaffResource;
use App\Support\AuditContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateStaffAction::class)->execute(
            $data,
            auth()->user(),
            AuditContext::fromRequest(request()),
        );
    }
}
