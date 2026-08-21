<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\Item;
use App\Support\AnalyticsClock;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

class AnalyticsOperationalSummaryWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->is_active === true
            && in_array(auth()->user()?->role, [UserRole::Owner, UserRole::Staff], true);
    }

    protected function getStats(): array
    {
        try {
            $asOf = app(AnalyticsClock::class)->now();
            $eligibleBefore = AnalyticsClock::storage(
                $asOf->subHours(30 * 24),
            );
            $summary = Item::query()->where('is_active', true)->selectRaw(
                'COUNT(*) AS active_count,
                SUM(CASE WHEN created_at <= ? THEN 1 ELSE 0 END) AS eligible_count,
                SUM(CASE WHEN created_at > ? THEN 1 ELSE 0 END) AS learning_count,
                MIN(CASE WHEN created_at > ? THEN created_at ELSE NULL END) AS oldest_learning_created_at,
                MAX(analytics_calculated_at) AS last_calculated_at',
                [$eligibleBefore, $eligibleBefore, $eligibleBefore],
            )->first();

            $lastCalculated = $summary?->last_calculated_at
                ? AnalyticsClock::business(CarbonImmutable::parse($summary->last_calculated_at, 'UTC'))->format('d M Y H:i')
                : 'Belum ada';
            $learningProgress = 0;
            if ($summary?->oldest_learning_created_at) {
                $createdAt = AnalyticsClock::business(CarbonImmutable::parse($summary->oldest_learning_created_at, 'UTC'));
                $learningProgress = min(99, max(0, (int) floor(
                    $createdAt->diffInHours($asOf) * 100 / (30 * 24),
                )));
            }

            return [
                Stat::make('Item Aktif', (int) ($summary?->active_count ?? 0)),
                Stat::make('Eligible', (int) ($summary?->eligible_count ?? 0))->color('success'),
                Stat::make('Sedang Belajar', (int) ($summary?->learning_count ?? 0))
                    ->description("Progress item terlama {$learningProgress}% menuju histori 30 hari")->color('info'),
                Stat::make('Kalkulasi Terakhir', $lastCalculated),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [Stat::make('Ringkasan Operasional', 'Analytics tidak tersedia')->color('danger')
                ->description('Data gagal dimuat. Coba muat ulang.')];
        }
    }
}
