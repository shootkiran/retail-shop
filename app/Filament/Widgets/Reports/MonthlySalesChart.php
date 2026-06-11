<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Sale;
use Filament\Widgets\LineChartWidget;

class MonthlySalesChart extends LineChartWidget
{
    protected ?string $heading = 'Monthly Sales';

    protected function getData(): array
    {
        $rows = Sale::query()
            ->selectRaw("strftime('%Y-%m', sold_at) as month, sum(grand_total) as total")
            ->whereNotNull('sold_at')
            ->groupBy('month')
            ->orderBy('month')
            ->limit(12)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => $rows->pluck('total')->map(fn ($value) => (float) $value)->all(),
                    'borderColor' => '#2563eb',
                    'backgroundColor' => '#93c5fd',
                ],
            ],
            'labels' => $rows->pluck('month')->all(),
        ];
    }
}
