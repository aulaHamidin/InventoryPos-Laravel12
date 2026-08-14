<?php

namespace App\Filament\Resources\ItemResource\Pages;

use App\Actions\Inventory\DeactivateItemAction;
use App\Actions\Inventory\UpdateItemAction;
use App\Filament\Resources\ItemResource;
use App\Support\AuditContext;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditItem extends EditRecord
{
    protected static string $resource = ItemResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateItemAction::class)->execute((int) $record->getKey(), $data, auth()->user(), AuditContext::fromRequest(request()));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('deactivate')
                ->label('Nonaktifkan')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => (bool) $this->record->is_active)
                ->action(function (): void {
                    app(DeactivateItemAction::class)->execute((int) $this->record->getKey(), auth()->user(), AuditContext::fromRequest(request()));
                    $this->redirect(ItemResource::getUrl('index'));
                }),
        ];
    }
}
