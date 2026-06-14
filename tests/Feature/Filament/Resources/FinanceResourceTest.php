<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Filament\Resources\BankAccounts\Pages\ViewBankAccount;
use App\Filament\Resources\CashRegisters\Pages\ListCashRegisters;
use App\Filament\Resources\CashRegisters\Pages\ViewCashRegister;
use App\Filament\Resources\FinancialEntries\Pages\ListFinancialEntries;
use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\ViewPaymentMethod;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Filament\Widgets\Reports\FinanceSummaryStats;
use App\Filament\Widgets\Reports\MonthlySalesChart;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\FinancialEntry;
use App\Models\PaymentMethod;
use App\Support\CurrentBusiness;
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

    public function test_daybook_page_renders_ledger_columns_and_balances(): void
    {
        $business = Business::create([
            'name' => 'Daybook Test Business',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $this->user->forceFill([
            'current_business_id' => $business->id,
            'office_type' => 'back_office',
            'is_active' => true,
        ])->save();

        $this->user->businesses()->attach($business->id, [
            'role' => 'admin',
            'office_type' => 'back_office',
            'is_active' => true,
        ]);

        app(CurrentBusiness::class)->clear();

        $account = BankAccount::create([
            'name' => 'Daybook Bank',
            'bank_name' => 'Test Bank',
            'account_number' => '999888777',
            'account_type' => 'checking',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        FinancialEntry::create([
            'business_id' => $business->id,
            'accountable_type' => BankAccount::class,
            'accountable_id' => $account->id,
            'entry_type' => 'manual',
            'direction' => 'credit',
            'amount' => 100,
            'entry_date' => now()->subDays(10),
            'reference' => 'OB-001',
            'notes' => 'Opening balance',
        ]);

        FinancialEntry::create([
            'business_id' => $business->id,
            'accountable_type' => BankAccount::class,
            'accountable_id' => $account->id,
            'entry_type' => 'manual',
            'direction' => 'debit',
            'amount' => 50,
            'entry_date' => now()->subDays(2),
            'reference' => 'EX-001',
            'notes' => 'Office expense',
        ]);

        FinancialEntry::create([
            'business_id' => $business->id,
            'accountable_type' => BankAccount::class,
            'accountable_id' => $account->id,
            'entry_type' => 'manual',
            'direction' => 'credit',
            'amount' => 300,
            'entry_date' => now()->subDay(),
            'reference' => 'IN-001',
            'notes' => 'Customer receipt',
        ]);

        Livewire::test(ListFinancialEntries::class)
            ->set('day', now()->subDay()->toDateString())
            ->assertOk()
            ->assertSee('Date')
            ->assertSee('Opening balance')
            ->assertSee('Closing balance')
            ->assertSee('Balance b/d')
            ->assertSee('Balance c/d')
            ->assertSee('Expenses')
            ->assertSee('Incomes')
            ->assertSee('50.00')
            ->assertSee('300.00');
    }

    public function test_dashboard_page_can_render(): void
    {
        $page = app(Dashboard::class);

        $this->assertSame([
            FinanceSummaryStats::class,
            MonthlySalesChart::class,
        ], $page->getWidgets());

        Livewire::test(Dashboard::class)->assertOk();
    }

    public function test_payment_method_type_is_selectable(): void
    {
        $paymentMethod = PaymentMethod::factory()->make([
            'type' => 'card',
        ]);

        Livewire::test(CreatePaymentMethod::class)
            ->fillForm([
                'name' => $paymentMethod->name,
                'type' => $paymentMethod->type,
                'description' => $paymentMethod->description,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas(PaymentMethod::class, [
            'name' => $paymentMethod->name,
            'type' => 'card',
        ]);
    }

    public function test_bank_account_view_page_can_render_with_transactions(): void
    {
        $business = Business::create([
            'name' => 'Finance Test Business',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $this->user->forceFill([
            'current_business_id' => $business->id,
            'office_type' => 'back_office',
            'is_active' => true,
        ])->save();

        $this->user->businesses()->attach($business->id, [
            'role' => 'admin',
            'office_type' => 'back_office',
            'is_active' => true,
        ]);

        app(CurrentBusiness::class)->clear();

        $account = BankAccount::create([
            'name' => 'Main Bank',
            'bank_name' => 'Test Bank',
            'account_number' => '1234567890',
            'account_type' => 'checking',
            'opening_balance' => 1000,
            'is_active' => true,
        ]);

        FinancialEntry::create([
            'accountable_type' => BankAccount::class,
            'accountable_id' => $account->id,
            'entry_type' => 'manual',
            'direction' => 'credit',
            'amount' => 250,
            'entry_date' => now(),
            'reference' => 'TST-001',
            'notes' => 'Seeded transaction for view page test',
            'business_id' => $business->id,
        ]);

        Livewire::test(ViewBankAccount::class, ['record' => $account->getKey()])
            ->assertOk();
    }

    public function test_cash_register_view_page_can_render(): void
    {
        $register = CashRegister::create([
            'name' => 'Main Register',
            'code' => 'REG-001',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        Livewire::test(ViewCashRegister::class, ['record' => $register->getKey()])
            ->assertOk();
    }

    public function test_payment_method_view_page_can_render(): void
    {
        $paymentMethod = PaymentMethod::factory()->create();

        Livewire::test(ViewPaymentMethod::class, ['record' => $paymentMethod->getKey()])
            ->assertOk();
    }
}
