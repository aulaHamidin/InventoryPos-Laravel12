<?php

namespace App\Filament\Admin\Resources;

use App\Actions\Billing\RejectManualPaymentAction;
use App\Actions\Billing\VerifyManualPaymentAction;
use App\Enums\AdminRole;
use App\Enums\BillingPaymentStatus;
use App\Models\BillingPayment;
use App\Support\AuditContext;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class BillingPaymentResource extends Resource
{
    protected static ?string $model = BillingPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Manual Payment';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('invoice.invoice_number')->label('Invoice')->searchable(),
            Tables\Columns\TextColumn::make('tenant.nama_toko')->label('Tenant'),
            Tables\Columns\TextColumn::make('amount')->label('Nominal')->money('IDR'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('recorder.name')->label('Pencatat'),
            Tables\Columns\TextColumn::make('verifier.name')->label('Verifier'),
        ])->actions([
            Tables\Actions\Action::make('verify')->label('Verifikasi')->requiresConfirmation()->visible(fn (BillingPayment $record): bool => $record->status === BillingPaymentStatus::Pending)
                ->action(fn (BillingPayment $record) => app(VerifyManualPaymentAction::class)->execute(auth('admin')->user(), $record, context: AuditContext::fromRequest(request()))),
            Tables\Actions\Action::make('reject')->label('Tolak')->color('danger')->visible(fn (BillingPayment $record): bool => $record->status === BillingPaymentStatus::Pending)
                ->form([Forms\Components\Textarea::make('reason')->label('Alasan')->required()->minLength(3)->maxLength(1000)])
                ->action(fn (BillingPayment $record, array $data) => app(RejectManualPaymentAction::class)->execute(auth('admin')->user(), $record, $data['reason'], AuditContext::fromRequest(request()))),
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
        return ['index' => BillingPaymentResource\Pages\ListBillingPayments::route('/')];
    }
}
