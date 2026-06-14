<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Filament\Resources\Vendors\Pages\ViewVendor;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\FinancialEntry;
use App\Models\Vendor;
use App\Support\CurrentBusiness;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class VendorResourceTest extends FilamentTestCase
{
    public function test_list_page_can_render(): void
    {
        Livewire::test(ListVendors::class)->assertOk();
    }

    public function test_can_create_vendor(): void
    {
        $vendorData = Vendor::factory()->make();

        Livewire::test(CreateVendor::class)
            ->fillForm([
                'name' => $vendorData->name,
                'contact_person' => $vendorData->contact_person,
                'email' => $vendorData->email,
                'phone' => '9800000000',
                'address' => $vendorData->address,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas(Vendor::class, [
            'name' => $vendorData->name,
            'email' => $vendorData->email,
        ]);
    }

    public function test_can_update_vendor(): void
    {
        $vendor = Vendor::factory()->create();
        $updatedData = [
            'name' => 'Updated Vendor',
            'contact_person' => 'Updated Contact',
            'email' => 'updated_vendor@example.test',
            'phone' => '1234567890',
            'address' => '123 Updated Street',
        ];

        Livewire::test(EditVendor::class, ['record' => $vendor->getKey()])
            ->fillForm([
                'name' => $updatedData['name'],
                'contact_person' => $updatedData['contact_person'],
                'email' => $updatedData['email'],
                'phone' => $updatedData['phone'],
                'address' => $updatedData['address'],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas(Vendor::class, [
            'id' => $vendor->id,
            'name' => $updatedData['name'],
            'email' => $updatedData['email'],
        ]);
    }

    public function test_can_delete_vendor(): void
    {
        $vendor = Vendor::factory()->create();

        Livewire::test(ListVendors::class)
            ->callTableAction(DeleteAction::class, $vendor);

        $this->assertModelMissing($vendor);
    }

    public function test_can_make_vendor_payment(): void
    {
        $business = Business::create([
            'name' => 'Vendor Payment Business',
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

        $vendor = Vendor::create([
            'name' => 'Vendor To Pay',
            'email' => 'payable@example.test',
            'phone' => '9800000001',
            'contact_person' => 'Accounts',
            'address' => 'Supplier Street',
        ]);

        $bankAccount = BankAccount::create([
            'name' => 'Operating Bank',
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'account_type' => 'checking',
            'opening_balance' => 1000,
            'is_active' => true,
        ]);

        Livewire::test(ListVendors::class)
            ->callTableAction('makePayment', $vendor, data: [
                'entry_date' => now()->toDateString(),
                'account_type' => 'bank',
                'account_id' => $bankAccount->id,
                'amount' => 250,
                'reference' => 'VP-001',
                'notes' => 'Vendor payment test',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $this->assertDatabaseHas(FinancialEntry::class, [
            'entry_type' => 'vendor_payment',
            'direction' => 'debit',
            'amount' => 250,
            'reference' => 'VP-001',
        ]);
    }

    public function test_view_page_can_render(): void
    {
        $vendor = Vendor::factory()->create();

        Livewire::test(ViewVendor::class, ['record' => $vendor->getKey()])
            ->assertOk();
    }
}
