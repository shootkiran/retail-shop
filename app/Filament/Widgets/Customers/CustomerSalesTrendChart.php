<?php

namespace App\Filament\Widgets\Customers;

use App\Models\Customer;
use Carbon\CarbonPeriod;
use Filament\Widgets\LineChartWidget;

class CustomerSalesTrendChart extends LineChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Sales and Payments Trend';

    public ?Customer $record = null;

    protected function getData(): array
    {
        if (! $this->record) {
            return [
                'datasets' => [
                    [
                        'label' => 'Sales',
                        'data' => [],
                        'borderColor' => '#2563eb',
                        'backgroundColor' => 'rgba(37, 99, 235, 0.15)',
                    ],
                    [
                        'label' => 'Payments',
                        'data' => [],
                        'borderColor' => '#16a34a',
                        'backgroundColor' => 'rgba(22, 163, 74, 0.15)',
                    ],
                ],
                'labels' => [],
            ];
        }

        $months = collect(CarbonPeriod::create(now()->startOfMonth()->subMonths(11), '1 month', now()->startOfMonth()))
            ->map(fn ($date): string => $date->format('Y-m'))
            ->all();

        $salesByMonth = $this->record->sales()
            ->selectRaw("strftime('%Y-%m', sold_at) as month, sum(grand_total) as total")
            ->whereNotNull('sold_at')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->map(fn ($value): float => (float) $value)
            ->all();

        $paymentsByMonth = $this->record->payments()
            ->selectRaw("strftime('%Y-%m', payment_date) as month, sum(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->map(fn ($value): float => (float) $value)
            ->all();

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => array_map(fn (string $month): float => $salesByMonth[$month] ?? 0.0, $months),
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.15)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
                [
                    'label' => 'Payments',
                    'data' => array_map(fn (string $month): float => $paymentsByMonth[$month] ?? 0.0, $months),
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.15)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
            ],
            'labels' => $months,
        ];
    }
}
