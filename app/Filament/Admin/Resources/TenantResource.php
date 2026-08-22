<?php

namespace App\Filament\Admin\Resources;

use App\Actions\Billing\CreateSubscriptionAction;
use App\Actions\Billing\GenerateInvoiceAction;
use App\Actions\Impersonation\StartImpersonationAction;
use App\Actions\Platform\ResetOwnerAccessAction;
use App\Actions\Platform\SetTenantOperationalStatusAction;
use App\Enums\AdminRole;
use App\Enums\OperationalStatus;
use App\Filament\Admin\Resources\TenantResource\Pages;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\AuditContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Tenant';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nama_toko')->label('Nama toko')->required()->maxLength(255),
            Forms\Components\TextInput::make('owner_name')->label('Nama Owner')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
            Forms\Components\TextInput::make('no_hp')->label('Nomor HP')->required()->maxLength(20),
            Forms\Components\TextInput::make('password')->password()->required()->minLength(12),
            Forms\Components\TextInput::make('password_confirmation')->password()->required()->same('password')->dehydrated(false),
            Forms\Components\Select::make('plan_id')->label('Plan')->options(
                Plan::query()->where('is_active', true)->where('is_internal', false)->pluck('name', 'id'),
            )->required(),
            Forms\Components\Toggle::make('trial')->label('Mulai dengan trial')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('nama_toko')->label('Tenant')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner'),
            Tables\Columns\TextColumn::make('owner.email')->label('Email'),
            Tables\Columns\TextColumn::make('owner.no_hp')->label('Nomor HP'),
            Tables\Columns\TextColumn::make('currentSubscription.plan.name')->label('Plan'),
            Tables\Columns\TextColumn::make('currentSubscription.status')->label('Subscription')->badge(),
            Tables\Columns\TextColumn::make('operational_status')->label('Operasional')->badge(),
        ])->actions([
            Tables\Actions\Action::make('impersonate')->label('Impersonate read-only')->icon('heroicon-o-eye')
                ->form([Forms\Components\Textarea::make('reason')->label('Alasan')->required()->minLength(10)->maxLength(1000)])
                ->visible(fn (Tenant $record): bool => $record->operational_status === OperationalStatus::Active && $record->owner?->is_active === true)
                ->action(function (Tenant $record, array $data) {
                    app(StartImpersonationAction::class)->execute(
                        auth('admin')->user(), $record->owner, $data['reason'], request()->session()->getId(),
                        context: AuditContext::fromRequest(request()),
                    );

                    return redirect('/app');
                }),
            Tables\Actions\Action::make('new_subscription')->label('Subscription baru')
                ->visible(fn (Tenant $record): bool => self::superAdmin() && $record->currentSubscription === null)
                ->form([
                    Forms\Components\Select::make('plan_id')->label('Plan')->options(Plan::query()->where('is_active', true)->where('is_internal', false)->pluck('name', 'id'))->required(),
                    Forms\Components\Toggle::make('trial')->label('Trial')->default(false),
                ])->action(function (Tenant $record, array $data): void {
                    $plan = Plan::query()->findOrFail($data['plan_id']);
                    $subscription = app(CreateSubscriptionAction::class)->execute(auth('admin')->user(), $record, $plan, (bool) $data['trial'], $record->owner?->no_hp, context: AuditContext::fromRequest(request()));
                    if (! $data['trial']) {
                        app(GenerateInvoiceAction::class)->execute(auth('admin')->user(), $subscription, $plan, context: AuditContext::fromRequest(request()));
                    }
                }),
            Tables\Actions\Action::make('reset_owner')->label('Reset akses Owner')->visible(fn (): bool => self::superAdmin())
                ->form([
                    Forms\Components\TextInput::make('password')->password()->required()->minLength(12),
                    Forms\Components\TextInput::make('password_confirmation')->password()->required()->same('password')->dehydrated(false),
                ])->action(fn (Tenant $record, array $data) => app(ResetOwnerAccessAction::class)->execute(auth('admin')->user(), $record->owner, $data['password'], AuditContext::fromRequest(request()))),
            Tables\Actions\Action::make('ban')->label('Ban')->color('danger')->requiresConfirmation()->visible(fn (Tenant $record): bool => self::superAdmin() && $record->operational_status === OperationalStatus::Active)
                ->action(fn (Tenant $record) => app(SetTenantOperationalStatusAction::class)->execute(auth('admin')->user(), $record, OperationalStatus::Banned, AuditContext::fromRequest(request()))),
            Tables\Actions\Action::make('unban')->label('Unban')->visible(fn (Tenant $record): bool => self::superAdmin() && $record->operational_status === OperationalStatus::Banned)
                ->action(fn (Tenant $record) => app(SetTenantOperationalStatusAction::class)->execute(auth('admin')->user(), $record, OperationalStatus::Active, AuditContext::fromRequest(request()))),
        ])->bulkActions([]);
    }

    public static function canCreate(): bool
    {
        return self::superAdmin();
    }

    public static function canViewAny(): bool
    {
        return auth('admin')->user() instanceof Admin;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTenants::route('/'), 'create' => Pages\CreateTenant::route('/create')];
    }

    private static function superAdmin(): bool
    {
        return auth('admin')->user()?->role === AdminRole::SuperAdmin;
    }
}
