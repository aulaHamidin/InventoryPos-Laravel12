<?php

namespace App\Filament\Admin\Resources\SupportResource\Pages;

use App\Filament\Admin\Resources\SupportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListSupport extends ListRecords
{
    protected static string $resource = SupportResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
