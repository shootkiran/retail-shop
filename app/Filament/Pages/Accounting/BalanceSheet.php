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

class BalanceSheet extends Page
{
    use RequiresBackOffice;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';

    protected static UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Balance Sheet';

    protected static ?string $slug = 'accounting/balance-sheet';

    protected string $view = 'filament.pages.accounting.balance-sheet';

    #[Url]
    public ?string $endDate = null;

    public function mount(): void
    {
        $this->endDate = $this->endDate ?: now()->endOfMonth()->toDateString();
    }

    public function getBalanceSheetDataProperty(): array
    {
        $business = app(CurrentBusiness::class)->get();
        if (! $business instanceof Business) {
            return [
                'assets' => [],
                'total_assets' => 0.0,
                'liabilities' => [],
                'total_liabilities' => 0.0,
                'equity' => [],
                'current_earnings' => 0.0,
                'total_equity' => 0.0,
                'total_liabilities_and_equity' => 0.0,
            ];
        }

        return app(FinancialStatementsService::class)->getBalanceSheet($business, $this->endDate);
    }

    public function formatMoney(float $amount): string
    {
        return config('retail.currency.symbol', 'रू').' '.number_format($amount, 2);
    }
}
