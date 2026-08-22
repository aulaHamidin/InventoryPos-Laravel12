<?php

namespace App\Filament\Admin\Resources;

use App\Actions\Billing\GenerateInvoiceAction;
use App\Enums\AdminRole;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\AuditContext;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Subscription';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('tenant.nama_toko')->label('Tenant')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('plan.name')->label('Plan'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('starts_at')->label('Mulai')->dateTime('d M Y H:i', 'Asia/Jakarta'),
            Tables\Columns\TextColumn::make('ends_at')->label('Berakhir')->dateTime('d M Y H:i', 'Asia/Jakarta')->sortable(),
        ])->actions([
            Tables\Actions\Action::make('invoice')->label('Buat invoice')->visible(fn (Subscription $record): bool => auth('admin')->user()?->role === AdminRole::SuperAdmin && $record->status !== SubscriptionStatus::Expired)
                ->form([Forms\Components\Select::make('plan_id')->label('Target plan')->options(Plan::query()->where('is_active', true)->where('is_internal', false)->pluck('name', 'id'))->required()])
                ->action(fn (Subscription $record, array $data) => app(GenerateInvoiceAction::class)->execute(auth('admin')->user(), $record, Plan::query()->findOrFail($data['plan_id']), context: AuditContext::fromRequest(request()))),
        ])->bulkActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => SubscriptionResource\Pages\ListSubscriptions::route('/')];
    }
}
