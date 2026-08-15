<?php

namespace App\Filament\Resources;

use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Filament\Resources\StockOpnameResource\Pages;
use App\Models\StockOpname;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Persediaan';

    protected static ?string $modelLabel = 'Stock Opname';

    protected static ?string $pluralModelLabel = 'Stock Opname';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount([
            'details',
            'details as counted_details_count' => fn ($query) => $query->whereNotNull('counted_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('scope_type')->label('Scope')->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof StockOpnameScope ? $state->label() : (string) $state),
                Tables\Columns\TextColumn::make('rack.nama')->label('Rak')->placeholder('Semua rak'),
                Tables\Columns\TextColumn::make('progress')->label('Progress')
                    ->state(fn (StockOpname $record): string => "{$record->counted_details_count} / {$record->details_count}"),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof StockOpnameStatus ? $state->label() : (string) $state)
                    ->color(fn ($state): string => $state instanceof StockOpnameStatus ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('creator.name')->label('Dibuat oleh'),
                Tables\Columns\TextColumn::make('started_at')->label('Mulai')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('completed_at')->label('Selesai')->dateTime('d M Y H:i')->placeholder('—'),
            ])
            ->defaultSort('started_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('count')
                    ->label(fn (StockOpname $record): string => $record->status === StockOpnameStatus::Draft ? 'Lanjut Hitung' : 'Lihat Hasil')
                    ->icon(fn (StockOpname $record): string => $record->status === StockOpnameStatus::Draft ? 'heroicon-o-calculator' : 'heroicon-o-eye')
                    ->url(fn (StockOpname $record): string => static::getUrl('count', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockOpnames::route('/'),
            'count' => Pages\CountStockOpname::route('/{record}/count'),
        ];
    }
}
