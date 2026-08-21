<?php

namespace App\Filament\Widgets;

use App\Enums\PosPaymentMethod;
use App\Models\PosPayment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PosPaymentMethodSummary extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected static bool $isLazy = true;

    protected function getStats(): array
    {
        $summaries = PosPayment::query()
            ->whereNotNull('paid_at')
            ->selectRaw('method, COUNT(*) AS payment_count, SUM(amount) AS payment_total, SUM(refunded_amount) AS refunded_total')
            ->groupBy('method')
            ->get()
            ->keyBy(fn (PosPayment $payment): string => $payment->method->value);

        return collect(PosPaymentMethod::cases())->map(function (PosPaymentMethod $method) use ($summaries): Stat {
            $summary = $summaries->get($method->value);
            $total = (float) ($summary?->payment_total ?? 0);
            $refunded = (float) ($summary?->refunded_total ?? 0);

            return Stat::make($method->label(), 'Rp'.number_format($total - $refunded, 0, ',', '.'))
                ->description(($summary?->payment_count ?? 0).' payment · refund tercatat Rp'.number_format($refunded, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($method === PosPaymentMethod::Cash ? 'success' : 'primary');
        })->all();
    }
}
