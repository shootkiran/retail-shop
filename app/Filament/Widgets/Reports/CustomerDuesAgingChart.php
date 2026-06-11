<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Customer;
use Filament\Widgets\DoughnutChartWidget;

class CustomerDuesAgingChart extends DoughnutChartWidget
{
    protected ?string $heading = 'Customer Dues Aging';

    protected function getData(): array
    {
        $today = now();

        $dues = Customer::query()
            ->where('outstanding_balance', '>', 0)
            ->get()
            ->groupBy(function (Customer $customer) use ($today): string {
                $oldestSale = $customer->sales()->where('amount_due', '>', 0)->orderBy('sold_at')->first();
                $ageDays = $oldestSale?->sold_at?->diffInDays($today) ?? 0;

                return match (true) {
                    $ageDays < 30 => '0-29 days',
                    $ageDays < 60 => '30-59 days',
                    $ageDays < 90 => '60-89 days',
                    default => '90+ days',
                };
            })
            ->map(fn ($group) => $group->count());

        return [
            'datasets' => [
                [
                    'data' => [
                        (int) ($dues['0-29 days'] ?? 0),
                        (int) ($dues['30-59 days'] ?? 0),
                        (int) ($dues['60-89 days'] ?? 0),
                        (int) ($dues['90+ days'] ?? 0),
                    ],
                    'backgroundColor' => ['#22c55e', '#eab308', '#f97316', '#dc2626'],
                ],
            ],
            'labels' => ['0-29 days', '30-59 days', '60-89 days', '90+ days'],
        ];
    }
}
