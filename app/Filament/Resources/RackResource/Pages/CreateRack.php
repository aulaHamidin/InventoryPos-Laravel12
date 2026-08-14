<?php

namespace App\Filament\Resources\RackResource\Pages;

use App\Actions\MasterData\CreateMasterDataAction;
use App\Filament\Resources\RackResource;
use App\Models\Rack;
use App\Support\AuditContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRack extends CreateRecord
{
    protected static string $resource = RackResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateMasterDataAction::class)->execute(Rack::class, $data, auth()->user(), AuditContext::fromRequest(request()));
    }
}
