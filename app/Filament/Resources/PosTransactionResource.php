<?php

namespace App\Filament\Resources;

use App\Actions\Reports\QueueReportExportAction;
use App\Enums\PosPaymentMethod;
use App\Enums\PosTransactionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\PosTransactionResource\Pages;
use App\Models\PosTransaction;
use App\Support\AuditContext;
use App\Support\PosRefundCalculator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Transaksi')->schema([
                Infolists\Components\TextEntry::make('invoice_number')->label('Invoice'),
                Infolists\Components\TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                Infolists\Components\TextEntry::make('cashier.name')->label('Kasir'),
                Infolists\Components\TextEntry::make('status')->label('Status Transaksi')
                    ->formatStateUsing(fn ($state): string => $state->label())->badge(),
                Infolists\Components\TextEntry::make('subtotal_amount')->label('Bruto')->money('IDR'),
                Infolists\Components\TextEntry::make('discount_amount')->label('Diskon')->money('IDR'),
                Infolists\Components\TextEntry::make('total_amount')->label('Net')->money('IDR'),
            ])->columns(3),
            Infolists\Components\Section::make('Pembayaran')->schema([
                Infolists\Components\TextEntry::make('payment.method')->label('Metode')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? 'Belum Dibayar'),
                Infolists\Components\TextEntry::make('payment.status')->label('Status Payment')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? 'Belum Dibayar')->badge(),
                Infolists\Components\TextEntry::make('payment.amount')->label('Payment')->money('IDR')->placeholder('-'),
                Infolists\Components\TextEntry::make('payment.paid_at')->label('Waktu Konfirmasi')->dateTime('d M Y H:i')->placeholder('-'),
                Infolists\Components\TextEntry::make('payment.confirmedBy.name')->label('Dikonfirmasi Oleh')->placeholder('-'),
                Infolists\Components\TextEntry::make('payment.manual_reference')->label('Referensi Manual')->placeholder('-'),
                Infolists\Components\TextEntry::make('payment.confirmation_note')->label('Catatan Konfirmasi')->placeholder('-')->columnSpanFull(),
                Infolists\Components\TextEntry::make('payment.refunded_amount')->label('Refund Tercatat')->money('IDR')->placeholder('Rp0')
                    ->visible(fn (): bool => static::ownerCanManage()),
                Infolists\Components\TextEntry::make('refund_obligation_amount')->label('Kewajiban Refund')
                    ->state(function (PosTransaction $record): string {
                        if ($record->payment === null) {
                            return '0.00';
                        }
                        $record->payment->setRelation('transaction', $record);

                        return PosRefundCalculator::obligation($record->payment);
                    })->money('IDR')->visible(fn (): bool => static::ownerCanManage()),
                Infolists\Components\TextEntry::make('refund_due_amount')->label('Refund Due')
                    ->state(function (PosTransaction $record): string {
                        if ($record->payment === null) {
                            return '0.00';
                        }
                        $record->payment->setRelation('transaction', $record);

                        return PosRefundCalculator::due($record->payment);
                    })->money('IDR')->visible(fn (): bool => static::ownerCanManage()),
                Infolists\Components\TextEntry::make('refund_resolution')->label('Penyelesaian Refund')
                    ->state(function (PosTransaction $record): string {
                        if ($record->payment === null) {
                            return '-';
                        }
                        $record->payment->setRelation('transaction', $record);
                        $obligation = PosRefundCalculator::obligation($record->payment);
                        if ($obligation === '0.00') {
                            return 'Tidak ada kewajiban';
                        }

                        return PosRefundCalculator::due($record->payment) === '0.00'
                            ? 'Refund selesai'
                            : 'Refund tertunda';
                    })->badge()->visible(fn (): bool => static::ownerCanManage()),
            ])->columns(3),
            Infolists\Components\Section::make('Item dan Retur')->schema([
                Infolists\Components\RepeatableEntry::make('items')->label('')->schema([
                    Infolists\Components\TextEntry::make('item.nama')->label('Item'),
                    Infolists\Components\TextEntry::make('qty')->label('Terjual'),
                    Infolists\Components\TextEntry::make('returned_qty')->label('Diretur'),
                    Infolists\Components\TextEntry::make('harga_saat_transaksi')->label('Harga')->money('IDR'),
                    Infolists\Components\TextEntry::make('discount_amount')->label('Diskon')->money('IDR'),
                ])->columns(5),
            ]),
            Infolists\Components\Section::make('Histori Stok dan Audit')->schema([
                Infolists\Components\RepeatableEntry::make('stockMovements')->label('Pergerakan Stok')->schema([
                    Infolists\Components\TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                    Infolists\Components\TextEntry::make('movement_type')->label('Tipe'),
                    Infolists\Components\TextEntry::make('item.nama')->label('Item'),
                    Infolists\Components\TextEntry::make('qty')->label('Qty'),
                    Infolists\Components\TextEntry::make('note')->label('Catatan')->placeholder('-'),
                ])->columns(5),
                Infolists\Components\RepeatableEntry::make('auditLogs')->label('Audit Transaksi')->schema([
                    Infolists\Components\TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                    Infolists\Components\TextEntry::make('action')->label('Event'),
                    Infolists\Components\TextEntry::make('actor_type')->label('Actor'),
                    Infolists\Components\TextEntry::make('actor_id')->label('Actor ID')->placeholder('System'),
                ])->columns(4),
                Infolists\Components\RepeatableEntry::make('payment.auditLogs')->label('Audit Refund')->schema([
                    Infolists\Components\TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                    Infolists\Components\TextEntry::make('action')->label('Event'),
                    Infolists\Components\TextEntry::make('actor_id')->label('Actor ID'),
                    Infolists\Components\TextEntry::make('new_values.note')->label('Catatan')->placeholder('-'),
                ])->columns(4),
            ])->collapsible()->visible(fn (): bool => static::ownerCanManage()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->searchable()->sortable()->label('Invoice'),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('cashier.name')->label('Kasir'),
                Tables\Columns\TextColumn::make('payment.method')->label('Metode')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? 'Belum Dibayar')
                    ->placeholder('Belum Dibayar'),
                Tables\Columns\TextColumn::make('payment.status')->label('Status Payment')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? 'Belum Dibayar')
                    ->badge()->placeholder('Belum Dibayar'),
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
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options(collect(PosPaymentMethod::cases())->mapWithKeys(
                        fn (PosPaymentMethod $method) => [$method->value => $method->label()],
                    )->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $method): Builder => $query->whereHas(
                            'payments', fn (Builder $paymentQuery): Builder => $paymentQuery->where('method', $method),
                        ),
                    )),
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
                        Forms\Components\Select::make('payment_method')->label('Metode Pembayaran')
                            ->options(collect(PosPaymentMethod::cases())->mapWithKeys(
                                fn (PosPaymentMethod $method) => [$method->value => $method->label()],
                            )->all()),
                    ])
                    ->visible(fn (): bool => static::ownerCanManage())
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
                    ->alpineClickHandler('window.print()')
                    ->visible(fn (): bool => static::ownerCanManage()),
            ])
            ->actions([Tables\Actions\ViewAction::make()])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'cashier', 'items.item', 'payment.confirmedBy',
        ]);

        if (static::ownerCanManage()) {
            return $query->with(['payment.auditLogs', 'stockMovements.item', 'auditLogs']);
        }

        return $query->where('cashier_id', auth()->id());
    }

    public static function ownerCanManage(): bool
    {
        return auth()->user()?->role === UserRole::Owner
            && auth()->user()?->is_active === true;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosTransactions::route('/'),
            'view' => Pages\ViewPosTransaction::route('/{record}'),
        ];
    }
}
