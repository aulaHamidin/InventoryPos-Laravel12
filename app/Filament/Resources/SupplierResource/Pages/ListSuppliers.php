<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->visible(
                fn (): bool => auth()->user()?->role === UserRole::Owner,
            ),
        ];
    }
}
