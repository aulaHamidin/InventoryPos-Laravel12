<?php

namespace App\Filament\Resources;

use App\Actions\Reports\QueueReportExportAction;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\Item;
use App\Models\StockMovement;
use App\Support\AuditContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Inventori';

    protected static ?string $modelLabel = 'Riwayat Stok';

    protected static ?string $pluralModelLabel = 'Riwayat Stok';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('item_id')->relationship('item', 'nama')->disabled(),
            Forms\Components\TextInput::make('movement_type')->disabled(),
            Forms\Components\TextInput::make('qty')->disabled(),
            Forms\Components\TextInput::make('direction')->disabled(),
            Forms\Components\TextInput::make('harga_satuan')->disabled(),
            Forms\Components\Textarea::make('note')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->label('Tanggal'),
                Tables\Columns\TextColumn::make('item.nama')->searchable()->sortable()->label('Barang'),
                Tables\Columns\TextColumn::make('movement_type')->badge()->label('Tipe'),
                Tables\Columns\TextColumn::make('direction')
                    ->formatStateUsing(fn (string $state): string => $state === 'in' ? 'Masuk' : 'Keluar')
                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger')->badge(),
                Tables\Columns\TextColumn::make('qty')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('User'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('movement_type')->options([
                    'stock_in' => 'Stock In', 'stock_out' => 'Stock Out', 'sale' => 'Penjualan',
                    'customer_return' => 'Retur Pelanggan', 'supplier_return' => 'Retur Supplier',
                    'damaged' => 'Rusak', 'adjustment' => 'Penyesuaian', 'opname_adjustment' => 'Opname',
                ]),
                Tables\Filters\SelectFilter::make('item_id')->options(fn () => Item::orderBy('nama')->pluck('nama', 'id'))->searchable(),
                Tables\Filters\Filter::make('created_at')->form([
                    Forms\Components\DatePicker::make('from')->label('Dari'),
                    Forms\Components\DatePicker::make('until')->label('Sampai'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                    ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->headerActions([
                Tables\Actions\Action::make('queue_export')
                    ->label('Export Pergerakan')->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Forms\Components\Select::make('format')->options(['pdf' => 'PDF', 'xlsx' => 'Excel'])->required(),
                        Forms\Components\DatePicker::make('date_from'),
                        Forms\Components\DatePicker::make('date_to'),
                        Forms\Components\Select::make('item_id')->options(fn () => Item::orderBy('nama')->pluck('nama', 'id'))->searchable(),
                        Forms\Components\Select::make('movement_type')->options([
                            'stock_in' => 'Stock In', 'stock_out' => 'Stock Out', 'sale' => 'Penjualan',
                            'adjustment' => 'Penyesuaian', 'damaged' => 'Rusak',
                        ]),
                    ])
                    ->action(function (array $data): void {
                        $format = $data['format'];
                        unset($data['format']);
                        app(QueueReportExportAction::class)->execute('movement', $format, $data, auth()->user(), AuditContext::fromRequest(request()));
                        Notification::make()->title('Export pergerakan masuk antrean')->success()->send();
                    }),
                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->extraAttributes(['class' => 'fi-no-print'])
                    ->alpineClickHandler('window.print()'),
            ])
            ->actions([Tables\Actions\ViewAction::make()])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'view' => Pages\ViewStockMovement::route('/{record}'),
        ];
    }
}
