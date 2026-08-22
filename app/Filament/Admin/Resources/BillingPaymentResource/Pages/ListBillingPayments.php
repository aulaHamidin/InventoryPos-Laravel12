<?php

namespace App\Filament\Admin\Resources\BillingPaymentResource\Pages;

use App\Filament\Admin\Resources\BillingPaymentResource;
use Filament\Resources\Pages\ListRecords;

final class ListBillingPayments extends ListRecords
{
    protected static string $resource = BillingPaymentResource::class;
}
