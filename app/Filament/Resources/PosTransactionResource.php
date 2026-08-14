<?php

namespace App\Filament\Resources;

use App\Actions\Reports\QueueReportExportAction;
use App\Enums\PosTransactionStatus;
use App\Filament\Resources\PosTransactionResource\Pages;
use App\Models\PosTransaction;
use App\Support\AuditContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PosTransactionResource extends Resource
{
    protected static ?string $model = PosTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Penjualan';

    protected static ?string $modelLabel = 'Transaksi POS';

    protected static ?string $pluralModelLabel = 'Transaksi POS';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('invoice_number')->disabled(),
            Forms\Components\TextInput::make('subtotal_amount')->disabled(),
            Forms\Components\TextInput::make('discount_amount')->disabled(),
            Forms\Components\TextInput::make('total_amount')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->searchable()->sortable()->label('Invoice'),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('cashier.name')->label('Kasir'),
                Tables\Columns\TextColumn::make('subtotal_amount')->label('Bruto')->money('IDR'),
                Tables\Columns\TextColumn::make('discount_amount')->label('Diskon')->money('IDR'),
                Tables\Columns\TextColumn::make('total_amount')->label('Net')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof PosTransactionStatus ? $state->label() : (string) $state)
                    ->color(fn ($state): string => match ($state) {
                        PosTransactionStatus::Completed => 'success',
                        PosTransactionStatus::PendingPayment => 'warning',
                        PosTransactionStatus::Failed, PosTransactionStatus::Voided => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(
                    collect(PosTransactionStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()
                ),
                Tables\Filters\Filter::make('created_at')->form([
                    Forms\Components\DatePicker::make('from'),
                    Forms\Components\DatePicker::make('until'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                    ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->headerActions([
                Tables\Actions\Action::make('queue_export')
                    ->label('Export POS')->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Forms\Components\Select::make('format')->options(['pdf' => 'PDF', 'xlsx' => 'Excel'])->required(),
                        Forms\Components\DatePicker::make('date_from'),
                        Forms\Components\DatePicker::make('date_to'),
                        Forms\Components\Select::make('status')->options(
                            collect(PosTransactionStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()
                        ),
                    ])
                    ->action(function (array $data): void {
                        $format = $data['format'];
                        unset($data['format']);
                        app(QueueReportExportAction::class)->execute('pos', $format, $data, auth()->user(), AuditContext::fromRequest(request()));
                        Notification::make()->title('Export POS masuk antrean')->success()->send();
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
            'index' => Pages\ListPosTransactions::route('/'),
            'view' => Pages\ViewPosTransaction::route('/{record}'),
        ];
    }
}
