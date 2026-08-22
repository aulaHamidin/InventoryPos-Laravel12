<?php

namespace App\Filament\Admin\Resources\SupportResource\Pages;

use App\Actions\Admin\CreateSupportAction;
use App\Filament\Admin\Resources\SupportResource;
use App\Support\AuditContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateSupport extends CreateRecord
{
    protected static string $resource = SupportResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateSupportAction::class)->execute(auth('admin')->user(), $data['name'], $data['email'], $data['password'], AuditContext::fromRequest(request()));
    }
}
