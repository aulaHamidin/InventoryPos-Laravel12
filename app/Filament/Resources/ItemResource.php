<?php

namespace App\Filament\Resources;

use App\Actions\Inventory\AdjustStockAction;
use App\Actions\Inventory\StockInAction;
use App\Actions\Inventory\UpsertItemSupplierAction;
use App\Actions\Reports\QueueReportExportAction;
use App\Filament\Resources\ItemResource\Pages;
use App\Models\Item;
use App\Models\Supplier;
use App\Support\AuditContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'Barang';

    protected static ?string $pluralModelLabel = 'Barang';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('kode')
                ->helperText('Kosongkan saat membuat untuk menghasilkan kode dari kategori.')
                ->disabledOn('edit')->dehydrated(fn (string $operation): bool => $operation !== 'edit'),
            Forms\Components\TextInput::make('barcode')->maxLength(100),
            Forms\Components\TextInput::make('nama')->required()->maxLength(255),
            Forms\Components\Select::make('category_id')->relationship('category', 'nama')->required()->searchable(),
            Forms\Components\Select::make('rack_id')->relationship('rack', 'nama')->searchable(),
            Forms\Components\TextInput::make('satuan')->required()->default('Pcs')->maxLength(50),
            Forms\Components\TextInput::make('harga_beli')->required()->numeric()->minValue(0)->default(0),
            Forms\Components\TextInput::make('harga_jual')->required()->numeric()->minValue(0)->default(0),
            Forms\Components\TextInput::make('stok_minimal')->required()->numeric()->minValue(0)->default(0),
            Forms\Components\Select::make('threshold_mode')->options(['manual' => 'Manual', 'auto_velocity' => 'Otomatis'])->default('manual'),
            Forms\Components\TextInput::make('lead_time_days')->numeric()->minValue(0)->default(0),
            Forms\Components\TextInput::make('safety_stock_days')->numeric()->minValue(0)->default(0),
            Forms\Components\DatePicker::make('exp_date'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('nama')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('category.nama')->label('Kategori')->sortable(),
                Tables\Columns\TextColumn::make('stok_saat_ini')
                    ->label('Stok')->sortable()->weight('bold')
                    ->color(fn (Item $record): string => $record->stok_saat_ini <= $record->stok_minimal ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state, Item $record): string => "{$state} {$record->satuan}"),
                Tables\Columns\TextColumn::make('harga_jual')->money('IDR')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Aktif'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->relationship('category', 'nama')->label('Kategori'),
                Tables\Filters\Filter::make('low_stock')->label('Stok Rendah')
                    ->query(fn ($query) => $query->whereColumn('stok_saat_ini', '<=', 'stok_minimal')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('queue_export')
                    ->label('Export Laporan')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Forms\Components\Select::make('format')->options(['pdf' => 'PDF', 'xlsx' => 'Excel'])->required(),
                        Forms\Components\Toggle::make('low_stock')->label('Hanya stok rendah'),
                    ])
                    ->action(function (array $data): void {
                        app(QueueReportExportAction::class)->execute(
                            'stock', $data['format'], ['low_stock' => $data['low_stock'] ?? false],
                            auth()->user(), AuditContext::fromRequest(request()),
                        );
                        Notification::make()->title('Export masuk antrean')->body('Progres tersedia melalui endpoint/status export.')->success()->send();
                    }),
                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->extraAttributes(['class' => 'fi-no-print'])
                    ->alpineClickHandler('window.print()'),
            ])
            ->actions([
                Tables\Actions\Action::make('stock_in')
                    ->label('Stok Masuk')->icon('heroicon-o-arrow-down-tray')->color('success')
                    ->form([
                        Forms\Components\Placeholder::make('stok_sebelum')->content(fn (Item $record): string => (string) $record->stok_saat_ini),
                        Forms\Components\TextInput::make('qty')->numeric()->minValue(1)->required(),
                        Forms\Components\TextInput::make('harga_satuan')->numeric()->minValue(0)->required()->prefix('Rp'),
                        Forms\Components\Select::make('supplier_id')->options(fn () => Supplier::orderBy('nama')->pluck('nama', 'id'))->searchable(),
                        Forms\Components\Textarea::make('note'),
                    ])
                    ->action(function (Item $record, array $data): void {
                        app(StockInAction::class)->execute(
                            (int) $record->getKey(), (int) $data['qty'], $data['harga_satuan'], auth()->user(),
                            isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
                            note: $data['note'] ?? null, context: AuditContext::fromRequest(request()),
                        );
                        Notification::make()->title('Stok berhasil ditambahkan')->success()->send();
                    }),
                Tables\Actions\Action::make('adjust_stock')
                    ->label('Sesuaikan')->icon('heroicon-o-adjustments-horizontal')->color('warning')
                    ->form([
                        Forms\Components\Placeholder::make('stok_sebelum')->label('Stok sebelum')->content(fn (Item $record): string => (string) $record->stok_saat_ini),
                        Forms\Components\Select::make('direction')->options(['in' => 'Tambah', 'out' => 'Kurangi'])->required(),
                        Forms\Components\TextInput::make('qty')->label('Perubahan qty')->numeric()->minValue(1)->required(),
                        Forms\Components\Textarea::make('note')->label('Alasan')->required(),
                    ])
                    ->action(function (Item $record, array $data): void {
                        app(AdjustStockAction::class)->execute(
                            (int) $record->getKey(), (int) $data['qty'], $data['direction'], $data['note'],
                            auth()->user(), AuditContext::fromRequest(request()),
                        );
                        Notification::make()->title('Stok berhasil disesuaikan')->success()->send();
                    }),
                Tables\Actions\Action::make('supplier')
                    ->label('Supplier')->icon('heroicon-o-truck')
                    ->form([
                        Forms\Components\Select::make('supplier_id')->options(fn () => Supplier::orderBy('nama')->pluck('nama', 'id'))->required()->searchable(),
                        Forms\Components\TextInput::make('supplier_sku'),
                        Forms\Components\TextInput::make('harga_beli_terakhir')->numeric()->minValue(0),
                        Forms\Components\TextInput::make('lead_time_days')->numeric()->minValue(0),
                        Forms\Components\Toggle::make('is_preferred')->label('Preferred supplier'),
                    ])
                    ->action(function (Item $record, array $data): void {
                        app(UpsertItemSupplierAction::class)->execute(
                            (int) $record->getKey(), (int) $data['supplier_id'], $data,
                            auth()->user(), AuditContext::fromRequest(request()),
                        );
                        Notification::make()->title('Supplier item tersimpan')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}
