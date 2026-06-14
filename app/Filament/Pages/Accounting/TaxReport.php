<?php

namespace App\Filament\Pages\Accounting;

use App\Filament\Concerns\RequiresBackOffice;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalLine;
use App\Models\Business;
use App\Support\CurrentBusiness;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

class TaxReport extends Page
{
    use RequiresBackOffice;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Tax / VAT Report';

    protected static ?string $slug = 'accounting/tax-report';

    protected string $view = 'filament.pages.accounting.tax-report';

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    public function mount(): void
    {
        $this->startDate = $this->startDate ?: now()->startOfMonth()->toDateString();
        $this->endDate = $this->endDate ?: now()->endOfMonth()->toDateString();
    }

    public function getTaxDataProperty(): array
    {
        $business = app(CurrentBusiness::class)->get();
        if (! $business instanceof Business) {
            return [
                'tax_output' => 0.0,
                'tax_input' => 0.0,
                'net_payable' => 0.0,
            ];
        }

        // 1. Calculate Tax Output (Sales Tax Payable - code 2120)
        $outputAccount = Account::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('code', '2120')
            ->first();

        $outputDebit = 0.0;
        $outputCredit = 0.0;

        if ($outputAccount) {
            $outputQuery = JournalLine::query()
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.business_id', $business->id)
                ->where('journal_lines.account_id', $outputAccount->id)
                ->whereBetween('journal_entries.entry_date', [$this->startDate, $this->endDate]);

            $outputDebit = (float) $outputQuery->sum('debit');
            $outputCredit = (float) $outputQuery->sum('credit');
        }

        // Tax Output is Credit (collected) - Debit (refunded)
        $taxOutput = $outputCredit - $outputDebit;

        // 2. Calculate Tax Input (Purchase Tax Paid - code 1320)
        $inputAccount = Account::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('code', '1320')
            ->first();

        $inputDebit = 0.0;
        $inputCredit = 0.0;

        if ($inputAccount) {
            $inputQuery = JournalLine::query()
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.business_id', $business->id)
                ->where('journal_lines.account_id', $inputAccount->id)
                ->whereBetween('journal_entries.entry_date', [$this->startDate, $this->endDate]);

            $inputDebit = (float) $inputQuery->sum('debit');
            $inputCredit = (float) $inputQuery->sum('credit');
        }

        // Tax Input is Debit (paid) - Credit (returned)
        $taxInput = $inputDebit - $inputCredit;

        return [
            'tax_output' => round($taxOutput, 2),
            'tax_input' => round($taxInput, 2),
            'net_payable' => round($taxOutput - $taxInput, 2),
        ];
    }

    public function formatMoney(float $amount): string
    {
        return config('retail.currency.symbol', 'रू').' '.number_format($amount, 2);
    }
}
