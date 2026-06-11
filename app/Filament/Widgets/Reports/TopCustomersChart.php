<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Sale;
use Filament\Widgets\BarChartWidget;

class TopCustomersChart extends BarChartWidget
{
    protected ?string $heading = 'Top 10 Customers';

    protected function getData(): array
    {
        $rows = Sale::query()
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->selectRaw('coalesce(customers.name, \'Walk-in\') as name, sum(sales.grand_total) as total_sales')
            ->groupBy('name')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Sales Value',
                    'data' => $rows->pluck('total_sales')->map(fn ($value) => (float) $value)->all(),
                    'backgroundColor' => '#7c3aed',
                ],
            ],
            'labels' => $rows->pluck('name')->all(),
        ];
    }
}
