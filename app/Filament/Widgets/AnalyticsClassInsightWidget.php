<?php

namespace App\Filament\Widgets;

use App\Enums\MovementClass;
use App\Models\Item;
use App\Support\AnalyticsClock;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Throwable;

class AnalyticsClassInsightWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        try {
            $counts = Item::query()->where('is_active', true)
                ->select('movement_class', DB::raw('COUNT(*) AS aggregate'))
                ->groupBy('movement_class')->get()
                ->mapWithKeys(fn (Item $item): array => [$item->movement_class->value => (int) $item->aggregate]);
            $asOf = app(AnalyticsClock::class)->now();
            $eligibleBefore = AnalyticsClock::storage(
                $asOf->subHours(30 * 24),
            );
            $noMovement = Item::query()->where('is_active', true)
                ->where('created_at', '<=', $eligibleBefore)
                ->whereDoesntHave('movements', fn ($query) => $query
                    ->whereIn('movement_type', ['sale', 'sale_void', 'customer_return'])
                    ->where('created_at', '>=', $eligibleBefore)
                    ->where('created_at', '<', AnalyticsClock::storage($asOf)))
                ->count();

            return [
                Stat::make('Fast Moving', (int) ($counts[MovementClass::Fast->value] ?? 0))->color('success'),
                Stat::make('Belum Terklasifikasi', (int) ($counts[MovementClass::Unclassified->value] ?? 0))
                    ->description('Menunggu histori 30 hari atau backfill')->color('gray'),
                Stat::make('Dead Stock', (int) ($counts[MovementClass::Dead->value] ?? 0))->color('danger'),
                Stat::make('Eligible Tanpa Movement', $noMovement)->color('warning'),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [Stat::make('Insight Kelas', 'Analytics tidak tersedia')->color('danger')
                ->description('Data gagal dimuat. Coba muat ulang.')];
        }
    }
}
