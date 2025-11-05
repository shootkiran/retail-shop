<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\EditPurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\PaymentMethod;
use App\Models\ProductItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Vendor;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class PurchaseResourceTest extends FilamentTestCase
{
    public function test_list_page_can_render(): void
    {
        Livewire::test(ListPurchases::class)->assertOk();
    }

    public function test_can_create_purchase(): void
    {
        $vendor = Vendor::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create();
        $productItem = ProductItem::factory()
            ->for($vendor)
            ->create();

        $formData = [
            'vendor_id' => (string) $vendor->id,
            'payment_method_id' => (string) $paymentMethod->id,
            'status' => 'ordered',
            'tax_amount' => 20,
            'amount_paid' => 100,
            'total_amount' => 200,
            'discount_amount' => 10,
            'grand_total' => 210,
            'amount_due' => 110,
            'items' => [
                [
                    'product_item_id' => (string) $productItem->id,
                    'quantity' => 4,
                    'unit_cost' => 50,
                    'discount_amount' => 10,
                    'total_amount' => 190,
                ],
            ],
        ];

        $component = Livewire::test(CreatePurchase::class);

        $component->set('data', $formData);

        $component->call('create');

        $component
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $purchase = Purchase::where('vendor_id', $vendor->id)->first();

        $this->assertNotNull($purchase);
        $this->assertEquals('ordered', $purchase->status);
        $this->assertEqualsWithDelta(210.0, (float) $purchase->grand_total, 0.01);
        $this->assertEqualsWithDelta(110.0, (float) $purchase->amount_due, 0.01);

        $this->assertDatabaseHas(PurchaseItem::class, [
            'purchase_id' => $purchase->id,
            'product_item_id' => $productItem->id,
            'quantity' => 4,
        ]);
    }

    public function test_can_update_purchase(): void
    {
        $purchase = Purchase::factory()
            ->has(PurchaseItem::factory(), 'items')
            ->create([
                'status' => 'draft',
            ]);
        $existingItem = $purchase->items()->first();

        $newVendor = Vendor::factory()->create();
        $newPaymentMethod = PaymentMethod::factory()->create();
        $newProductItem = ProductItem::factory()
            ->for($newVendor)
            ->create();

        $updateData = [
            'vendor_id' => (string) $newVendor->id,
            'payment_method_id' => (string) $newPaymentMethod->id,
            'status' => 'received',
            'tax_amount' => 10,
            'amount_paid' => 150,
            'total_amount' => 200,
            'discount_amount' => 5,
            'grand_total' => 205,
            'amount_due' => 55,
            'items' => [
                [
                    'id' => $existingItem?->getKey(),
                    'product_item_id' => (string) $newProductItem->id,
                    'quantity' => 5,
                    'unit_cost' => 40,
                    'discount_amount' => 5,
                    'total_amount' => 195,
                ],
            ],
        ];

        $component = Livewire::test(EditPurchase::class, ['record' => $purchase->getKey()]);

        $component->set('data', $updateData);

        $component
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $purchase->refresh();

        $this->assertEquals($newVendor->id, $purchase->vendor_id);
        $this->assertEquals($newPaymentMethod->id, $purchase->payment_method_id);
        $this->assertEquals('received', $purchase->status);
        $this->assertEqualsWithDelta(205.0, (float) $purchase->grand_total, 0.01);
        $this->assertEqualsWithDelta(55.0, (float) $purchase->amount_due, 0.01);

        $this->assertDatabaseHas(PurchaseItem::class, [
            'purchase_id' => $purchase->id,
            'product_item_id' => $newProductItem->id,
            'quantity' => 5,
        ]);
    }

    public function test_can_delete_purchase(): void
    {
        $purchase = Purchase::factory()
            ->has(PurchaseItem::factory(), 'items')
            ->create();

        Livewire::test(ListPurchases::class)
            ->callTableAction(DeleteAction::class, $purchase);

        $this->assertModelMissing($purchase);
        $this->assertDatabaseMissing(PurchaseItem::class, [
            'purchase_id' => $purchase->id,
        ]);
    }
}
