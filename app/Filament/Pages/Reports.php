<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Widgets\Reports\CustomerDuesAgingChart;
use App\Filament\Widgets\Reports\TopCustomersChart;
use App\Filament\Widgets\Reports\TopItemsChart;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Reports extends Page
{
    use RequiresBackOffice;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Reports';

    protected string $view = 'filament.pages.reports';

    protected function getHeaderWidgets(): array
    {
        return [
            TopItemsChart::class,
            TopCustomersChart::class,
            CustomerDuesAgingChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
