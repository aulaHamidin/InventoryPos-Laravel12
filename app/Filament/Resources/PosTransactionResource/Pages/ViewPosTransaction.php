<?php

namespace App\Filament\Resources\PosTransactionResource\Pages;

use App\Actions\Pos\MarkPosPaymentRefundedAction;
use App\Actions\Pos\ReturnPosTransactionAction;
use App\Actions\Pos\VoidPosTransactionAction;
use App\Enums\PosPaymentStatus;
use App\Enums\PosTransactionStatus;
use App\Filament\Resources\PosTransactionResource;
use App\Support\AuditContext;
use App\Support\PosRefundCalculator;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPosTransaction extends ViewRecord
{
    protected static string $resource = PosTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('void')
                ->label('Void Transaksi')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (): bool => PosTransactionResource::ownerCanManage()
                    && $this->record->status === PosTransactionStatus::Completed
                    && $this->record->items->every(fn ($line): bool => $line->returned_qty === 0))
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Textarea::make('reason')->label('Alasan')->required()->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    app(VoidPosTransactionAction::class)->execute(
                        $this->record->getKey(), $data['reason'], auth()->user(), AuditContext::fromRequest(request()),
                    );
                    Notification::make()->title('Void tercatat; refund wajib diproses')->warning()->send();
                    $this->redirect(PosTransactionResource::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('return')
                ->label('Retur Item')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (): bool => PosTransactionResource::ownerCanManage() && in_array($this->record->status, [
                    PosTransactionStatus::Completed, PosTransactionStatus::PartiallyReturned,
                ], true))
                ->form([
                    Forms\Components\Repeater::make('items')->label('Baris Retur')->schema([
                        Forms\Components\Select::make('pos_transaction_item_id')->label('Item')->required()
                            ->options(fn (): array => $this->record->items->filter(
                                fn ($line): bool => $line->returned_qty < $line->qty,
                            )->mapWithKeys(fn ($line): array => [
                                $line->id => "{$line->item?->nama} (sisa ".($line->qty - $line->returned_qty).')',
                            ])->all())
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Forms\Components\TextInput::make('qty')->label('Qty Tambahan')->numeric()->minValue(1)->required(),
                    ])->columns(2)->minItems(1)->defaultItems(1),
                ])
                ->action(function (array $data): void {
                    app(ReturnPosTransactionAction::class)->execute(
                        $this->record->getKey(), $data['items'], auth()->user(), AuditContext::fromRequest(request()),
                    );
                    Notification::make()->title('Retur dan kewajiban refund diperbarui')->warning()->send();
                    $this->redirect(PosTransactionResource::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('mark_refunded')
                ->label('Catat Refund')
                ->icon('heroicon-o-banknotes')
                ->visible(function (): bool {
                    if (! PosTransactionResource::ownerCanManage()) {
                        return false;
                    }
                    $payment = $this->record->payment;
                    if ($payment === null || ! in_array($payment->status, [
                        PosPaymentStatus::RefundRequired, PosPaymentStatus::PartiallyRefunded,
                    ], true)) {
                        return false;
                    }
                    $payment->setRelation('transaction', $this->record);

                    return PosRefundCalculator::due($payment) !== '0.00';
                })
                ->form([
                    Forms\Components\TextInput::make('refunded_amount')->label('Target Cumulative Refund')
                        ->numeric()->minValue(0)->required()->prefix('Rp'),
                    Forms\Components\Textarea::make('note')->label('Catatan Refund')->required()->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    app(MarkPosPaymentRefundedAction::class)->execute(
                        $this->record->payment->getKey(),
                        $data['refunded_amount'],
                        $data['note'],
                        auth()->user(),
                        AuditContext::fromRequest(request()),
                    );
                    Notification::make()->title('Refund cumulative tercatat')->success()->send();
                    $this->redirect(PosTransactionResource::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('print')
                ->label('Cetak / Simpan PDF')
                ->icon('heroicon-o-printer')
                ->extraAttributes(['class' => 'fi-no-print'])
                ->alpineClickHandler('window.print()')
                ->visible(fn (): bool => PosTransactionResource::ownerCanManage()),
        ];
    }
}
