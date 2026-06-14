<?php

namespace App\Filament\Widgets\Customers;

use App\Models\Customer;
use Filament\Widgets\DoughnutChartWidget;

class CustomerPaymentMethodsChart extends DoughnutChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Payment Methods Mix';

    public ?Customer $record = null;

    protected function getData(): array
    {
        if (! $this->record) {
            return [
                'datasets' => [
                    [
                        'data' => [0, 0, 0, 0],
                        'backgroundColor' => ['#f59e0b', '#2563eb', '#8b5cf6', '#10b981'],
                    ],
                ],
                'labels' => ['Cash', 'Bank Transfer', 'Cheque', 'Online Transfer'],
            ];
        }

        $methods = [
            'cash' => 'Cash',
            'bank' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'online' => 'Online Transfer',
        ];

        $payments = $this->record->payments()
            ->selectRaw('method, sum(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn ($value): float => (float) $value)
            ->all();

        return [
            'datasets' => [
                [
                    'data' => array_map(fn (string $method): float => $payments[$method] ?? 0.0, array_keys($methods)),
                    'backgroundColor' => ['#f59e0b', '#2563eb', '#8b5cf6', '#10b981'],
                ],
            ],
            'labels' => array_values($methods),
        ];
    }
}
