<?php

namespace App\Filament\Admin\Resources;

use App\Actions\Billing\RecordManualPaymentAction;
use App\Actions\Billing\VoidInvoiceAction;
use App\Enums\AdminRole;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\AuditContext;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Invoice';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('invoice_number')->label('Nomor')->searchable(),
            Tables\Columns\TextColumn::make('tenant.nama_toko')->label('Tenant')->searchable(),
            Tables\Columns\TextColumn::make('targetPlan.name')->label('Plan'),
            Tables\Columns\TextColumn::make('amount')->label('Nominal')->money('IDR')->visible(fn (): bool => self::superAdmin()),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('due_at')->label('Jatuh tempo')->dateTime('d M Y H:i', 'Asia/Jakarta'),
        ])->actions([
            Tables\Actions\Action::make('record_payment')->label('Catat pembayaran')->visible(fn (Invoice $record): bool => self::superAdmin() && $record->status === InvoiceStatus::Open)
                ->form([TextInput::make('reference')->label('Referensi')->maxLength(255)])
                ->action(fn (Invoice $record, array $data) => app(RecordManualPaymentAction::class)->execute(auth('admin')->user(), $record, $data['reference'] ?? null, AuditContext::fromRequest(request()))),
            Tables\Actions\Action::make('void')->label('Void')->color('danger')->requiresConfirmation()->visible(fn (Invoice $record): bool => self::superAdmin() && $record->status === InvoiceStatus::Open)
                ->action(fn (Invoice $record) => app(VoidInvoiceAction::class)->execute(auth('admin')->user(), $record, AuditContext::fromRequest(request()))),
        ])->bulkActions([]);
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
        return ['index' => InvoiceResource\Pages\ListInvoices::route('/')];
    }

    private static function superAdmin(): bool
    {
        return auth('admin')->user()?->role === AdminRole::SuperAdmin;
    }
}
