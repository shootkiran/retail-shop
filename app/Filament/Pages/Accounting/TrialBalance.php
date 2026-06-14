<?php

namespace App\Filament\Pages\Accounting;

use App\Filament\Concerns\RequiresBackOffice;
use App\Models\Business;
use App\Services\Accounting\FinancialStatementsService;
use App\Support\CurrentBusiness;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class TrialBalance extends Page
{
    use RequiresBackOffice;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';

    protected static UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Trial Balance';

    protected static ?string $slug = 'accounting/trial-balance';

    protected string $view = 'filament.pages.accounting.trial-balance';

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    public function mount(): void
    {
        $this->startDate = $this->startDate ?: now()->startOfMonth()->toDateString();
        $this->endDate = $this->endDate ?: now()->endOfMonth()->toDateString();
    }

    public function getTrialBalanceRowsProperty(): Collection
    {
        $business = app(CurrentBusiness::class)->get();
        if (! $business instanceof Business) {
            return collect();
        }

        return app(FinancialStatementsService::class)->getTrialBalance($business, $this->startDate, $this->endDate);
    }

    public function getDebitTotal(): float
    {
        return $this->getTrialBalanceRowsProperty()->sum('debit');
    }

    public function getCreditTotal(): float
    {
        return $this->getTrialBalanceRowsProperty()->sum('credit');
    }

    public function formatMoney(float $amount): string
    {
        return config('retail.currency.symbol', 'रू').' '.number_format($amount, 2);
    }
}
