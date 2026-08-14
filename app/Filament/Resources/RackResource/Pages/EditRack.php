<?php

namespace App\Filament\Resources\RackResource\Pages;

use App\Actions\MasterData\DeleteMasterDataAction;
use App\Actions\MasterData\UpdateMasterDataAction;
use App\Filament\Resources\RackResource;
use App\Models\Rack;
use App\Support\AuditContext;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRack extends EditRecord
{
    protected static string $resource = RackResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateMasterDataAction::class)->execute(Rack::class, (int) $record->getKey(), $data, auth()->user(), AuditContext::fromRequest(request()));
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
                        Rack::class,
                        (int) $this->record->getKey(),
                        auth()->user(),
                        AuditContext::fromRequest(request()),
                    );
                    $this->redirect(RackResource::getUrl('index'));
                }),
        ];
    }
}
