<?php

namespace App\Filament\Resources;

use App\Actions\Shopping\GenerateShoppingListAction;
use App\Actions\Shopping\SubmitShoppingListAction;
use App\Enums\ShoppingListStatus;
use App\Filament\Resources\ShoppingListResource\Pages;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Services\ImpersonationContext;
use App\Support\AuditContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShoppingListResource extends Resource
{
    protected static ?string $model = ShoppingList::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Pembelian';

    protected static ?string $modelLabel = 'Daftar Belanja';

    protected static ?string $pluralModelLabel = 'Daftar Belanja';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('submitted_at')->disabled(),
            Forms\Components\TextInput::make('completed_at')->disabled(),
            Forms\Components\Repeater::make('items')->relationship()->disabled()->schema([
                Forms\Components\Select::make('item_id')->relationship('item', 'nama'),
                Forms\Components\Select::make('supplier_id')->relationship('supplier', 'nama'),
                Forms\Components\TextInput::make('qty_disarankan'),
                Forms\Components\TextInput::make('qty_dibeli'),
                Forms\Components\TextInput::make('qty_received'),
            ])->columns(5)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Jumlah Item'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ShoppingListStatus ? $state->label() : (string) $state)
                    ->color(fn ($state): string => $state instanceof ShoppingListStatus ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('creator.name')->label('Dibuat oleh'),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(
                    collect(ShoppingListStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()
                ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generate')
                    ->label('Generate Otomatis')->icon('heroicon-o-sparkles')->color('warning')
                    ->action(function (): void {
                        $list = app(GenerateShoppingListAction::class)->execute(auth()->user(), AuditContext::fromRequest(request()));
                        Notification::make()
                            ->title($list ? 'Daftar belanja dibuat' : 'Tidak ada stok rendah')
                            ->body($list ? "{$list->items->count()} item ditambahkan." : 'Tidak ada list kosong yang dibuat.')
                            ->color($list ? 'success' : 'info')->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('submit')
                    ->label('Tandai Dibeli')->icon('heroicon-o-shopping-bag')->color('warning')
                    ->visible(fn (ShoppingList $record): bool => $record->status === ShoppingListStatus::Draft)
                    ->fillForm(fn (ShoppingList $record): array => [
                        'items' => $record->items()->with('item')->orderBy('id')->get()->map(fn ($row) => [
                            'shopping_list_item_id' => $row->id,
                            'item_name' => $row->item?->nama,
                            'is_checked' => $row->is_checked,
                            'supplier_id' => $row->supplier_id,
                            'qty_dibeli' => $row->qty_disarankan,
                        ])->all(),
                    ])
                    ->form([
                        Forms\Components\Repeater::make('items')->schema([
                            Forms\Components\Hidden::make('shopping_list_item_id'),
                            Forms\Components\TextInput::make('item_name')->disabled(),
                            Forms\Components\Toggle::make('is_checked')->label('Dibeli')->live(),
                            Forms\Components\Select::make('supplier_id')
                                ->options(fn () => Supplier::orderBy('nama')->pluck('nama', 'id'))
                                ->searchable(),
                            Forms\Components\TextInput::make('qty_dibeli')->numeric()->minValue(1),
                        ])->columns(4)->addable(false)->deletable(false)->reorderable(false),
                    ])
                    ->action(function (ShoppingList $record, array $data): void {
                        app(SubmitShoppingListAction::class)->execute(
                            (int) $record->getKey(), $data['items'], auth()->user(), AuditContext::fromRequest(request()),
                        );
                        Notification::make()->title('Daftar belanja ditandai dibeli')->success()->send();
                    }),
                Tables\Actions\Action::make('receive')
                    ->label('Terima Barang')->icon('heroicon-o-inbox-arrow-down')->color('success')
                    ->visible(fn (ShoppingList $record): bool => $record->status === ShoppingListStatus::Purchased)
                    ->url(fn (ShoppingList $record): string => route('filament.app.pages.receive-shopping-list', ['list' => $record->id])),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canViewAny(): bool
    {
        return ! ImpersonationContext::isSupport() && parent::canViewAny();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShoppingLists::route('/'),
            'view' => Pages\ViewShoppingList::route('/{record}'),
        ];
    }
}
