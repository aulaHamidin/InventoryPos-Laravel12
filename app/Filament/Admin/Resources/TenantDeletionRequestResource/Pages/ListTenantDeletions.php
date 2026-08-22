<?php

namespace App\Filament\Admin\Resources\TenantDeletionRequestResource\Pages;

use App\Filament\Admin\Resources\TenantDeletionRequestResource;
use Filament\Resources\Pages\ListRecords;

final class ListTenantDeletions extends ListRecords
{
    protected static string $resource = TenantDeletionRequestResource::class;
}
