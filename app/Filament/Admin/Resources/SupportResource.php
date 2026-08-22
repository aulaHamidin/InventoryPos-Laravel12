<?php

namespace App\Filament\Admin\Resources;

use App\Actions\Admin\ActivateSupportAction;
use App\Actions\Admin\DeactivateSupportAction;
use App\Actions\Admin\ResetSupportAccessAction;
use App\Actions\Admin\ResetSupportMfaAction;
use App\Enums\AdminRole;
use App\Filament\Admin\Resources\SupportResource\Pages;
use App\Models\Admin;
use App\Support\AuditContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class SupportResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Support';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
            Forms\Components\TextInput::make('password')->password()->revealable()->required()->minLength(12),
            Forms\Components\TextInput::make('password_confirmation')->password()->required()->same('password')->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nama')->searchable(),
            Tables\Columns\TextColumn::make('email')->searchable(),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            Tables\Columns\IconColumn::make('two_factor_confirmed_at')->label('MFA')->boolean(),
        ])->actions([
            Tables\Actions\Action::make('reset_access')->label('Reset akses')->visible(fn (): bool => self::allowed())
                ->form([
                    Forms\Components\TextInput::make('password')->password()->required()->minLength(12),
                    Forms\Components\TextInput::make('password_confirmation')->password()->required()->same('password')->dehydrated(false),
                ])->action(fn (Admin $record, array $data) => app(ResetSupportAccessAction::class)->execute(auth('admin')->user(), $record, $data['password'], AuditContext::fromRequest(request()))),
            Tables\Actions\Action::make('reset_mfa')->label('Reset MFA')->requiresConfirmation()->visible(fn (Admin $record): bool => self::allowed() && $record->two_factor_confirmed_at !== null)
                ->action(fn (Admin $record) => app(ResetSupportMfaAction::class)->execute(auth('admin')->user(), $record, AuditContext::fromRequest(request()))),
            Tables\Actions\Action::make('deactivate')->label('Nonaktifkan')->color('danger')->requiresConfirmation()->visible(fn (Admin $record): bool => self::allowed() && $record->is_active)
                ->action(fn (Admin $record) => app(DeactivateSupportAction::class)->execute(auth('admin')->user(), $record, AuditContext::fromRequest(request()))),
            Tables\Actions\Action::make('activate')->label('Aktifkan')->visible(fn (Admin $record): bool => self::allowed() && ! $record->is_active)
                ->action(fn (Admin $record) => app(ActivateSupportAction::class)->execute(auth('admin')->user(), $record, AuditContext::fromRequest(request()))),
        ])->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', AdminRole::Support);
    }

    public static function canViewAny(): bool
    {
        return self::allowed();
    }

    public static function canCreate(): bool
    {
        return self::allowed();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSupport::route('/'), 'create' => Pages\CreateSupport::route('/create')];
    }

    private static function allowed(): bool
    {
        return auth('admin')->user()?->role === AdminRole::SuperAdmin;
    }
}
