<?php

namespace App\Filament\Widgets\Reports;

use App\Models\SaleItem;
use Filament\Widgets\BarChartWidget;

class TopItemsChart extends BarChartWidget
{
    protected ?string $heading = 'Top 10 Items';

    protected function getData(): array
    {
        $rows = SaleItem::query()
            ->join('product_items', 'product_items.id', '=', 'sale_items.product_item_id')
            ->selectRaw('product_items.name as name, sum(sale_items.quantity_base) as total_quantity')
            ->groupBy('product_items.name')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Quantity Sold',
                    'data' => $rows->pluck('total_quantity')->map(fn ($value) => (float) $value)->all(),
                    'backgroundColor' => '#0f766e',
                ],
            ],
            'labels' => $rows->pluck('name')->all(),
        ];
    }
}
