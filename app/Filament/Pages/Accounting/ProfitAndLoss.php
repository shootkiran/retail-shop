<?php

namespace App\Filament\Pages\Accounting;

use App\Filament\Concerns\RequiresBackOffice;
use App\Models\Business;
use App\Services\Accounting\FinancialStatementsService;
use App\Support\CurrentBusiness;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

class ProfitAndLoss extends Page
{
    use RequiresBackOffice;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Profit & Loss Statement';

    protected static ?string $slug = 'accounting/profit-and-loss';

    protected string $view = 'filament.pages.accounting.profit-and-loss';

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    public function mount(): void
    {
        $this->startDate = $this->startDate ?: now()->startOfMonth()->toDateString();
        $this->endDate = $this->endDate ?: now()->endOfMonth()->toDateString();
    }

    public function getPnlDataProperty(): array
    {
        $business = app(CurrentBusiness::class)->get();
        if (! $business instanceof Business) {
            return [
                'revenue' => 0.0,
                'revenue_items' => [],
                'cogs' => 0.0,
                'gross_profit' => 0.0,
                'operating_expenses' => 0.0,
                'expense_items' => [],
                'net_profit' => 0.0,
            ];
        }

        return app(FinancialStatementsService::class)->getProfitAndLoss($business, $this->startDate, $this->endDate);
    }

    public function formatMoney(float $amount): string
    {
        return config('retail.currency.symbol', 'रू').' '.number_format($amount, 2);
    }
}
