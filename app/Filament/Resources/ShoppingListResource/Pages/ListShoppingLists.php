<?php

namespace App\Filament\Resources\ShoppingListResource\Pages;

use App\Filament\Resources\ShoppingListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShoppingLists extends ListRecords
{
    protected static string $resource = ShoppingListResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
