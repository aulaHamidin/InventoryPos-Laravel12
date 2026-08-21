<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Filament\Resources\ItemResource;
use App\Models\Item;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $lowStockCount = Item::where('is_active', true)
            ->whereColumn('stok_saat_ini', '<=', 'stok_minimal')
            ->count();

        if ($lowStockCount === 0) {
            return [
                Stat::make('Status Stok', 'Aman')
                    ->description('Semua barang di atas batas minimum')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success'),
            ];
        }

        return [
            Stat::make('Peringatan Stok', $lowStockCount.' Barang')
                ->description('Segera lakukan pembelian ulang')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->url(auth()->user()?->role === UserRole::Staff
                    ? ItemResource::getUrl('index', ['tableFilters' => ['low_stock' => ['isActive' => true]]])
                    : route('filament.app.resources.shopping-lists.index')),
        ];
    }
}
