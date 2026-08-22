<?php

namespace App\Filament\Admin\Resources;

use App\Actions\Billing\ClonePlanAction;
use App\Actions\Billing\DeactivatePlanAction;
use App\Enums\AdminRole;
use App\Enums\BillingInterval;
use App\Filament\Admin\Resources\PlanResource\Pages;
use App\Models\Admin;
use App\Models\Plan;
use App\Support\AuditContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Plan';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(64),
            Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(255),
            Forms\Components\Select::make('billing_interval')->label('Interval')->options([
                BillingInterval::Monthly->value => 'Bulanan', BillingInterval::Yearly->value => 'Tahunan',
            ])->required(),
            Forms\Components\TextInput::make('price')->label('Harga IDR')->numeric()->required()->minValue(0),
            Forms\Components\Toggle::make('is_trial')->label('Trial eligible')->live(),
            Forms\Components\TextInput::make('trial_days')->label('Hari trial')->integer()->minValue(1)->visible(fn (Forms\Get $get): bool => (bool) $get('is_trial')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('Nama')->searchable(),
            Tables\Columns\TextColumn::make('billing_interval')->label('Interval')->badge(),
            Tables\Columns\TextColumn::make('price')->label('Harga')->money('IDR')->visible(fn (): bool => self::superAdmin()),
            Tables\Columns\IconColumn::make('is_trial')->label('Trial')->boolean(),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([
            Tables\Actions\Action::make('clone')->label('Clone versi')->icon('heroicon-o-document-duplicate')
                ->visible(fn (): bool => self::superAdmin())
                ->form([
                    Forms\Components\TextInput::make('code')->required()->maxLength(64),
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('price')->numeric()->required()->minValue(0),
                ])->action(fn (Plan $record, array $data) => app(ClonePlanAction::class)->execute(
                    auth('admin')->user(), $record, $data['code'], $data['name'], (string) $data['price'], AuditContext::fromRequest(request()),
                )),
            Tables\Actions\Action::make('deactivate')->label('Nonaktifkan')->color('danger')->requiresConfirmation()
                ->visible(fn (Plan $record): bool => self::superAdmin() && $record->is_active && ! $record->is_internal)
                ->action(fn (Plan $record) => app(DeactivatePlanAction::class)->execute(auth('admin')->user(), $record, AuditContext::fromRequest(request()))),
        ])->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_internal', false);
    }

    public static function canCreate(): bool
    {
        return self::superAdmin();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPlans::route('/'), 'create' => Pages\CreatePlan::route('/create')];
    }

    private static function superAdmin(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && $admin->is_active && $admin->role === AdminRole::SuperAdmin;
    }
}
