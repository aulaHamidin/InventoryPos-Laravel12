<?php

namespace App\Filament\Resources\ItemResource\Pages;

use App\Actions\Inventory\CreateItemAction;
use App\Filament\Resources\ItemResource;
use App\Support\AuditContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateItem extends CreateRecord
{
    protected static string $resource = ItemResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateItemAction::class)->execute($data, auth()->user(), AuditContext::fromRequest(request()));
    }
}
