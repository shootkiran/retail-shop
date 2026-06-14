<?php

namespace Tests\Feature\Filament\Pages;

use App\Enums\Accounting\AccountCategory;
use App\Filament\Pages\Accounting\ChartOfAccounts;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubtype;
use App\Models\Business;
use App\Services\ChartOfAccountsService;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class ChartOfAccountsPageTest extends FilamentTestCase
{
    public function test_chart_of_accounts_page_can_render(): void
    {
        Livewire::test(ChartOfAccounts::class)
            ->assertOk()
            ->assertSee('Chart of Accounts')
            ->assertSee('Assets');
    }

    public function test_business_creation_seeds_chart_of_accounts(): void
    {
        $business = Business::create([
            'name' => 'COA Test Business',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $this->assertGreaterThan(0, AccountSubtype::withoutGlobalScopes()->where('business_id', $business->id)->count());
        $this->assertGreaterThan(0, Account::withoutGlobalScopes()->where('business_id', $business->id)->count());
    }

    public function test_generated_account_codes_stay_inside_the_subtype_range(): void
    {
        $business = Business::create([
            'name' => 'COA Code Range Business',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $subtype = AccountSubtype::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('category', AccountCategory::Asset->value)
            ->where('name', 'Cash and Cash Equivalents')
            ->firstOrFail();

        $account = app(ChartOfAccountsService::class)->createAccount($subtype, [
            'name' => 'Vault Cash',
            'description' => 'Additional cash reserve',
            'archived' => false,
        ]);

        $this->assertTrue(is_numeric($account->code));
        $this->assertGreaterThanOrEqual($subtype->code_start, (int) $account->code);
        $this->assertLessThanOrEqual($subtype->code_end, (int) $account->code);
    }

    public function test_page_scopes_accounts_to_current_business(): void
    {
        $business = Business::create([
            'name' => 'Primary COA Business',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $otherBusiness = Business::create([
            'name' => 'Other COA Business',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $otherSubtype = AccountSubtype::withoutGlobalScopes()
            ->where('business_id', $otherBusiness->id)
            ->where('category', AccountCategory::Asset->value)
            ->firstOrFail();

        Account::withoutGlobalScopes()->create([
            'business_id' => $otherBusiness->id,
            'account_subtype_id' => $otherSubtype->id,
            'code' => '1999',
            'name' => 'Hidden Other Business Account',
            'description' => null,
            'archived' => false,
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

        Livewire::test(ChartOfAccounts::class)
            ->assertOk()
            ->assertSee('Assets')
            ->assertDontSee('Hidden Other Business Account');
    }
}
