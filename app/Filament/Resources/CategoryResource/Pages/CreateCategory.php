<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Actions\MasterData\CreateMasterDataAction;
use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use App\Support\AuditContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateMasterDataAction::class)->execute(Category::class, $data, auth()->user(), AuditContext::fromRequest(request()));
    }
}
