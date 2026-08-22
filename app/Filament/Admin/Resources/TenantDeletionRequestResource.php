<?php

namespace App\Filament\Admin\Resources;

use App\Actions\Deletion\ApproveTenantDeletionAction;
use App\Actions\Deletion\CancelTenantDeletionAction;
use App\Actions\Deletion\RejectTenantDeletionAction;
use App\Enums\AdminRole;
use App\Enums\TenantDeletionStatus;
use App\Models\TenantDeletionRequest;
use App\Support\AuditContext;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class TenantDeletionRequestResource extends Resource
{
    protected static ?string $model = TenantDeletionRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-trash';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Deletion Review';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('tenant.nama_toko')->label('Tenant')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('reason')->label('Alasan')->limit(80),
            Tables\Columns\TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y H:i', 'Asia/Jakarta'),
            Tables\Columns\TextColumn::make('purge_after')->label('Purge setelah')->dateTime('d M Y H:i', 'Asia/Jakarta'),
        ])->actions([
            Tables\Actions\Action::make('approve')->label('Setujui')->requiresConfirmation()->visible(fn (TenantDeletionRequest $record): bool => $record->status === TenantDeletionStatus::Requested)
                ->action(fn (TenantDeletionRequest $record) => app(ApproveTenantDeletionAction::class)->execute(auth('admin')->user(), $record, context: AuditContext::fromRequest(request()))),
            Tables\Actions\Action::make('reject')->label('Tolak')->color('danger')->visible(fn (TenantDeletionRequest $record): bool => $record->status === TenantDeletionStatus::Requested)
                ->form([Forms\Components\Textarea::make('reason')->required()->minLength(3)->maxLength(1000)])
                ->action(fn (TenantDeletionRequest $record, array $data) => app(RejectTenantDeletionAction::class)->execute(auth('admin')->user(), $record, $data['reason'], AuditContext::fromRequest(request()))),
            Tables\Actions\Action::make('cancel_approval')->label('Batalkan approval')->requiresConfirmation()->visible(fn (TenantDeletionRequest $record): bool => $record->status === TenantDeletionStatus::Approved)
                ->action(fn (TenantDeletionRequest $record) => app(CancelTenantDeletionAction::class)->execute(auth('admin')->user(), $record, AuditContext::fromRequest(request()))),
        ])->bulkActions([]);
    }

    public static function canViewAny(): bool
    {
        return auth('admin')->user()?->role === AdminRole::SuperAdmin;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => TenantDeletionRequestResource\Pages\ListTenantDeletions::route('/')];
    }
}
