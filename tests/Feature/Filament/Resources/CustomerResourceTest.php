<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
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
        $customerData = Customer::factory()->make();

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => $customerData->name,
                'company' => $customerData->company,
                'email' => $customerData->email,
                'phone' => $customerData->phone,
                'billing_address' => $customerData->billing_address,
                'credit_limit' => $customerData->credit_limit,
                'outstanding_balance' => $customerData->outstanding_balance,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas(Customer::class, [
            'email' => $customerData->email,
            'name' => $customerData->name,
        ]);
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::factory()->create();
        $updatedData = Customer::factory()->make();

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
}
