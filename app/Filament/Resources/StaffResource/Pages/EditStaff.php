<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Actions\Staff\ActivateStaffAction;
use App\Actions\Staff\DeactivateStaffAction;
use App\Actions\Staff\ResetStaffAccessAction;
use App\Actions\Staff\UpdateStaffProfileAction;
use App\Filament\Resources\StaffResource;
use App\Support\AuditContext;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateStaffProfileAction::class)->execute(
            (int) $record->getKey(),
            $data,
            auth()->user(),
            AuditContext::fromRequest(request()),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reset_access')
                ->label('Reset Akses')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->form([
                    Forms\Components\TextInput::make('password')
                        ->label('Password Baru')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(12),
                    Forms\Components\TextInput::make('password_confirmation')
                        ->label('Konfirmasi Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->same('password')
                        ->dehydrated(),
                ])
                ->action(function (array $data): void {
                    $this->record = app(ResetStaffAccessAction::class)->execute(
                        (int) $this->record->getKey(),
                        $data,
                        auth()->user(),
                        AuditContext::fromRequest(request()),
                    );
                    Notification::make()->title('Akses Staff berhasil direset')->success()->send();
                }),
            Actions\Action::make('deactivate')
                ->label('Nonaktifkan')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => (bool) $this->record->is_active)
                ->action(function (): void {
                    $this->record = app(DeactivateStaffAction::class)->execute(
                        (int) $this->record->getKey(),
                        auth()->user(),
                        AuditContext::fromRequest(request()),
                    );
                    Notification::make()->title('Staff dinonaktifkan')->success()->send();
                }),
            Actions\Action::make('activate')
                ->label('Aktifkan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => ! $this->record->is_active)
                ->action(function (): void {
                    $this->record = app(ActivateStaffAction::class)->execute(
                        (int) $this->record->getKey(),
                        auth()->user(),
                        AuditContext::fromRequest(request()),
                    );
                    Notification::make()->title('Staff diaktifkan')->success()->send();
                }),
        ];
    }
}
