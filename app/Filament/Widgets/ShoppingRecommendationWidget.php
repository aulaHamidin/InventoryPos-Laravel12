<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ShoppingListResource;
use App\Models\Item;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

class ShoppingRecommendationWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        try {
            $count = Item::query()->where('is_active', true)
                ->whereColumn('stok_saat_ini', '<=', 'stok_minimal')->count();

            return [
                Stat::make('Rekomendasi Belanja', $count === 0 ? 'Tidak ada' : $count.' barang')
                    ->description($count === 0 ? 'Belum ada barang di bawah threshold' : 'Tinjau dan susun daftar belanja')
                    ->descriptionIcon($count === 0 ? 'heroicon-m-check-circle' : 'heroicon-m-shopping-cart')
                    ->color($count === 0 ? 'success' : 'warning')
                    ->url(ShoppingListResource::getUrl('index')),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [Stat::make('Rekomendasi Belanja', 'Tidak tersedia')->color('danger')
                ->description('Data gagal dimuat. Coba muat ulang.')];
        }
    }
}
