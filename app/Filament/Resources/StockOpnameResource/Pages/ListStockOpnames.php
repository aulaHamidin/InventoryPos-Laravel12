<?php

namespace App\Filament\Resources\StockOpnameResource\Pages;

use App\Actions\Opname\CreateOpnameAction;
use App\Exceptions\ApiProblemException;
use App\Filament\Resources\StockOpnameResource;
use App\Models\Rack;
use App\Support\AuditContext;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

class ListStockOpnames extends ListRecords
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_opname')
                ->label('Buat Sesi Opname')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\Select::make('scope_type')
                        ->label('Scope')
                        ->options(['partial' => 'Partial per rak', 'full' => 'Semua barang'])
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('rack_id')
                        ->label('Rak')
                        ->options(fn () => Rack::orderBy('nama')->pluck('nama', 'id'))
                        ->searchable()
                        ->required(fn (Forms\Get $get): bool => $get('scope_type') === 'partial')
                        ->visible(fn (Forms\Get $get): bool => $get('scope_type') === 'partial'),
                ])
                ->action(function (array $data): void {
                    try {
                        $opname = app(CreateOpnameAction::class)->execute(
                            $data['scope_type'],
                            auth()->user(),
                            $data['scope_type'] === 'partial' && isset($data['rack_id']) ? (int) $data['rack_id'] : null,
                            AuditContext::fromRequest(request()),
                        );
                    } catch (ApiProblemException|ValidationException $exception) {
                        $message = $exception instanceof ValidationException
                            ? (string) collect($exception->errors())->flatten()->first()
                            : $exception->getMessage();
                        Notification::make()->title('Sesi opname tidak dapat dibuat')->body($message)->danger()->send();

                        return;
                    }
                    Notification::make()->title('Sesi stock opname dibuat')->success()->send();
                    $this->redirect(StockOpnameResource::getUrl('count', ['record' => $opname]));
                }),
        ];
    }
}
