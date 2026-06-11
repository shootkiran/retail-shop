<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Customer;
use App\Models\FinancialEntry;
use App\Models\ProductItem;
use App\Models\Sale;
use App\Models\Vendor;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceSummaryStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $salesTotal = Sale::query()->sum('grand_total');
        $customers = Customer::query()->count();
        $products = ProductItem::query()->count();
        $vendors = Vendor::query()->count();
        $cashFlow = FinancialEntry::query()
            ->selectRaw("sum(case when direction = 'credit' then amount else amount * -1 end) as balance")
            ->value('balance') ?? 0;

        return [
            Stat::make('Sales', number_format((float) $salesTotal, 2))
                ->description('Total sales value')
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->color('success'),
            Stat::make('Customers', (string) $customers)
                ->description('Active customer records')
                ->icon(Heroicon::OutlinedUsers)
                ->color('info'),
            Stat::make('Products', (string) $products)
                ->description('Stocked product items')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('primary'),
            Stat::make('Vendors', (string) $vendors)
                ->description('Active vendor records')
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->color('warning'),
            Stat::make('Net Ledger', number_format((float) $cashFlow, 2))
                ->description('Credits minus debits')
                ->icon(Heroicon::OutlinedCalculator)
                ->color('gray'),
        ];
    }
}
