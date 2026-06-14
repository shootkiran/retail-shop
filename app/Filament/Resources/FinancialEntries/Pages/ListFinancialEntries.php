<?php

namespace App\Filament\Resources\FinancialEntries\Pages;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Resources\FinancialEntries\FinancialEntryResource;
use App\Models\FinancialEntry;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class ListFinancialEntries extends Page
{
    use RequiresBackOffice;

    protected static string $resource = FinancialEntryResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Daybook';

    protected string $view = 'filament.pages.daybook';

    public string $day;

    public function mount(): void
    {
        $this->day = now()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Entry')
                ->icon('heroicon-o-plus')
                ->url(FinancialEntryResource::getUrl('create')),
        ];
    }

    public function getEntriesQuery(): Builder
    {
        return FinancialEntry::query()
            ->whereDate('entry_date', $this->day);
    }

    public function getOpeningBalance(): float
    {
        return (float) (FinancialEntry::query()
            ->whereDate('entry_date', '<', $this->day)
            ->selectRaw("sum(case when direction = 'credit' then amount else amount * -1 end) as balance")
            ->value('balance') ?? 0);
    }

    public function getIncomeTotal(): float
    {
        return (float) $this->getEntriesQuery()
            ->where('direction', 'credit')
            ->sum('amount');
    }

    public function getExpenseTotal(): float
    {
        return (float) $this->getEntriesQuery()
            ->where('direction', 'debit')
            ->sum('amount');
    }

    public function getClosingBalance(): float
    {
        return $this->getOpeningBalance() + $this->getIncomeTotal() - $this->getExpenseTotal();
    }

    /**
     * @return Collection<int, array{date:string, particulars:string, reference:?string, amount:float, note:?string}>
     */
    public function getIncomeRows(): Collection
    {
        return $this->getEntriesQuery()
            ->where('direction', 'credit')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get()
            ->map(fn (FinancialEntry $entry): array => [
                'date' => $entry->entry_date?->toDateString() ?? '',
                'particulars' => $this->labelEntry($entry),
                'reference' => $entry->reference,
                'amount' => (float) $entry->amount,
                'note' => $entry->notes,
            ]);
    }

    /**
     * @return Collection<int, array{date:string, particulars:string, reference:?string, amount:float, note:?string}>
     */
    public function getExpenseRows(): Collection
    {
        return $this->getEntriesQuery()
            ->where('direction', 'debit')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get()
            ->map(fn (FinancialEntry $entry): array => [
                'date' => $entry->entry_date?->toDateString() ?? '',
                'particulars' => $this->labelEntry($entry),
                'reference' => $entry->reference,
                'amount' => (float) $entry->amount,
                'note' => $entry->notes,
            ]);
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return config('retail.currency.symbol', 'रू').' '.number_format((float) $amount, 2);
    }

    public function formatDate(string $date): string
    {
        return Carbon::parse($date)->format('d M Y');
    }

    public function updatedDay(): void
    {
        if ($this->day > now()->toDateString()) {
            $this->day = now()->toDateString();
        }
    }

    protected function labelEntry(FinancialEntry $entry): string
    {
        return match ($entry->entry_type) {
            'sale_receipt' => 'Sale receipt',
            'customer_payment' => 'Customer payment',
            'bank_deposit' => 'Bank deposit',
            'bank_withdrawal' => 'Bank withdrawal',
            'transfer_in' => 'Transfer in',
            'transfer_out' => 'Transfer out',
            'vendor_payment' => 'Vendor payment',
            default => 'Manual adjustment',
        };
    }
}
