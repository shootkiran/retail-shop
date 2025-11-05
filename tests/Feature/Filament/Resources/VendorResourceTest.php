<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Vendor;
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
                'phone' => $vendorData->phone,
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
}
