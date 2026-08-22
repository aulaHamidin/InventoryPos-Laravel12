<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\AdminRole;
use App\Support\Decimal;
use App\Support\MrrCalculator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class MrrOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth('admin')->user()?->role === AdminRole::SuperAdmin;
    }

    protected function getStats(): array
    {
        $totals = app(MrrCalculator::class)->totals();

        return [
            Stat::make('MRR Aktif', Decimal::formatIdr($totals['mrr']))->description('Active saja; yearly dibagi 12'),
            Stat::make('Nilai Past-due', Decimal::formatIdr($totals['past_due']))->color('warning')->description('Tidak termasuk MRR'),
        ];
    }
}
