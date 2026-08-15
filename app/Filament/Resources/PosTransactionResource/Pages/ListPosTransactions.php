<?php

namespace App\Filament\Resources\PosTransactionResource\Pages;

use App\Filament\Resources\PosTransactionResource;
use App\Filament\Widgets\PosPaymentMethodSummary;
use Filament\Resources\Pages\ListRecords;

class ListPosTransactions extends ListRecords
{
    protected static string $resource = PosTransactionResource::class;

    protected function getHeaderWidgets(): array
    {
        return [PosPaymentMethodSummary::class];
    }
}
