<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Filament\Resources\CashRegisters\Pages\ListCashRegisters;
use App\Filament\Resources\FinancialEntries\Pages\ListFinancialEntries;
use App\Filament\Resources\Units\Pages\ListUnits;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class FinanceResourceTest extends FilamentTestCase
{
    public function test_bank_accounts_list_page_can_render(): void
    {
        Livewire::test(ListBankAccounts::class)->assertOk();
    }

    public function test_cash_registers_list_page_can_render(): void
    {
        Livewire::test(ListCashRegisters::class)->assertOk();
    }

    public function test_units_list_page_can_render(): void
    {
        Livewire::test(ListUnits::class)->assertOk();
    }

    public function test_daybook_list_page_can_render(): void
    {
        Livewire::test(ListFinancialEntries::class)->assertOk();
    }
}
