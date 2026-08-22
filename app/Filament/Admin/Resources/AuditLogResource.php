<?php

namespace App\Filament\Admin\Resources;

use App\Enums\AdminRole;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Audit';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s', 'Asia/Jakarta')->sortable(),
            Tables\Columns\TextColumn::make('tenant_id')->label('Tenant ID'),
            Tables\Columns\TextColumn::make('actor_type')->label('Actor')->badge(),
            Tables\Columns\TextColumn::make('action')->searchable(),
            Tables\Columns\TextColumn::make('subject_type')->label('Subject')->limit(50),
            Tables\Columns\TextColumn::make('new_values')->label('Detail redacted')->formatStateUsing(fn ($state): string => json_encode($state, JSON_UNESCAPED_UNICODE) ?: '-')
                ->limit(120)->visible(fn (): bool => auth('admin')->user()?->role === AdminRole::SuperAdmin),
        ])->actions([])->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return AuditLog::query()->withoutGlobalScopes();
    }

    public static function canViewAny(): bool
    {
        return auth('admin')->user() !== null;
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
        return ['index' => AuditLogResource\Pages\ListAuditLogs::route('/')];
    }
}
