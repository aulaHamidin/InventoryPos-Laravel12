<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Actions\MasterData\DeleteMasterDataAction;
use App\Actions\MasterData\UpdateMasterDataAction;
use App\Filament\Resources\SupplierResource;
use App\Models\Supplier;
use App\Support\AuditContext;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateMasterDataAction::class)->execute(Supplier::class, (int) $record->getKey(), $data, auth()->user(), AuditContext::fromRequest(request()));
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
                        Supplier::class,
                        (int) $this->record->getKey(),
                        auth()->user(),
                        AuditContext::fromRequest(request()),
                    );
                    $this->redirect(SupplierResource::getUrl('index'));
                }),
        ];
    }
}
