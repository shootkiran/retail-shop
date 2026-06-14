<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Support\CurrentBusiness;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class CustomerResourceTest extends FilamentTestCase
{
    public function test_list_page_can_render(): void
    {
        Livewire::test(ListCustomers::class)->assertOk();
    }

    public function test_can_create_customer(): void
    {
        $customerData = Customer::factory()->make([
            'phone' => '9800000001',
        ]);

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => $customerData->name,
                'company' => $customerData->company,
                'email' => $customerData->email,
                'phone' => $customerData->phone,
                'billing_address' => $customerData->billing_address,
                'credit_limit' => $customerData->credit_limit,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas(Customer::class, [
            'email' => $customerData->email,
            'name' => $customerData->name,
            'outstanding_balance' => 0,
        ]);
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::factory()->create();
        $updatedData = Customer::factory()->make([
            'phone' => '9800000004',
        ]);

        Livewire::test(EditCustomer::class, ['record' => $customer->getKey()])
            ->fillForm([
                'name' => $updatedData->name,
                'company' => $updatedData->company,
                'email' => $updatedData->email,
                'phone' => $updatedData->phone,
                'billing_address' => $updatedData->billing_address,
                'credit_limit' => $updatedData->credit_limit,
                'outstanding_balance' => $updatedData->outstanding_balance,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas(Customer::class, [
            'id' => $customer->id,
            'name' => $updatedData->name,
            'email' => $updatedData->email,
        ]);
    }

    public function test_can_delete_customer(): void
    {
        $customer = Customer::factory()->create();

        Livewire::test(ListCustomers::class)
            ->callTableAction(DeleteAction::class, $customer);

        $this->assertModelMissing($customer);
    }

    public function test_customer_view_page_can_render_summary(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'View Customer',
            'company' => 'View Company',
            'email' => 'view-customer@example.test',
            'phone' => '9800000003',
            'billing_address' => 'Kathmandu, Nepal',
            'credit_limit' => 2500,
            'outstanding_balance' => 750,
        ]);

        Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
            ->assertOk()
            ->assertSee('Customer Profile')
            ->assertSee('Account Summary')
            ->assertSee($customer->name)
            ->assertSee($customer->company)
            ->assertSee($customer->email);
    }

    public function test_can_receive_customer_payment(): void
    {
        $business = Business::create([
            'name' => 'Customer Payment Business',
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

        $customer = Customer::create([
            'name' => 'Payment Customer',
            'email' => 'payment-customer@example.test',
            'phone' => '9800000002',
            'credit_limit' => 1000,
            'outstanding_balance' => 500,
        ]);

        $bankAccount = BankAccount::create([
            'name' => 'Customer Deposits',
            'bank_name' => 'Test Bank',
            'account_number' => '111222333',
            'account_type' => 'checking',
            'opening_balance' => 2000,
            'is_active' => true,
        ]);

        Livewire::test(ListCustomers::class)
            ->callTableAction('receivePayment', $customer, data: [
                'payment_date' => now()->toDateString(),
                'method' => 'bank',
                'account_type' => 'bank',
                'account_id' => $bankAccount->id,
                'amount' => 125,
                'reference' => 'CP-1001',
                'notes' => 'Partial payment received',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $customer->refresh();

        $this->assertSame('375.00', $customer->outstanding_balance);

        $this->assertDatabaseHas(CustomerPayment::class, [
            'customer_id' => $customer->id,
            'reference' => 'CP-1001',
            'amount' => 125,
        ]);
    }
}
