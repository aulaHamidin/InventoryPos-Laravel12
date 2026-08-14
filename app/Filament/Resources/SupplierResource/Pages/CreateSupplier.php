<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Actions\MasterData\CreateMasterDataAction;
use App\Filament\Resources\SupplierResource;
use App\Models\Supplier;
use App\Support\AuditContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateMasterDataAction::class)->execute(Supplier::class, $data, auth()->user(), AuditContext::fromRequest(request()));
    }
}
