<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportExportResource\Pages;
use App\Models\ReportExport;
use App\Services\ImpersonationContext;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReportExportResource extends Resource
{
    protected static ?string $model = ReportExport::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $modelLabel = 'Export Laporan';

    protected static ?string $pluralModelLabel = 'Export Laporan';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('report_type')
                    ->label('Laporan')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'stock' => 'Stok',
                        'movement' => 'Pergerakan Stok',
                        'pos' => 'POS',
                        default => $state,
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('format')
                    ->label('Format')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'processing' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progres')
                    ->formatStateUsing(fn (int $state): string => "{$state}%"),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('5s')
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Unduh')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ReportExport $record): string => route('reports.exports.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (ReportExport $record): bool => $record->status === 'completed' && filled($record->file_name)),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReportExports::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return ! ImpersonationContext::isSupport() && parent::canViewAny();
    }
}
