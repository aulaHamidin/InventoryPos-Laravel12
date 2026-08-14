<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Actions\MasterData\DeleteMasterDataAction;
use App\Actions\MasterData\UpdateMasterDataAction;
use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use App\Support\AuditContext;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateMasterDataAction::class)->execute(Category::class, (int) $record->getKey(), $data, auth()->user(), AuditContext::fromRequest(request()));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('delete')
                ->label('Hapus')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(DeleteMasterDataAction::class)->execute(
                        Category::class,
                        (int) $this->record->getKey(),
                        auth()->user(),
                        AuditContext::fromRequest(request()),
                    );
                    $this->redirect(CategoryResource::getUrl('index'));
                }),
        ];
    }
}
