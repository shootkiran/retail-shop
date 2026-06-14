<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Widgets\Reports\FinanceSummaryStats;
use App\Filament\Widgets\Reports\MonthlySalesChart;
use UnitEnum;

class Dashboard extends \Filament\Pages\Dashboard
{
    use RequiresBackOffice;

    protected static ?string $title = 'Dashboard';

    protected static UnitEnum|string|null $navigationGroup = null;

    protected static ?int $navigationSort = 0;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    public function getWidgets(): array
    {
        return [
            FinanceSummaryStats::class,
            MonthlySalesChart::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
