<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class SaleResourceTest extends FilamentTestCase
{
    public function test_list_page_can_render(): void
    {
        Livewire::test(ListSales::class)->assertOk();
    }

    public function test_can_create_sale(): void
    {
        $customer = Customer::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create();
        $productItem = ProductItem::factory()->create();

        $formData = [
            'customer_id' => (string) $customer->id,
            'payment_method_id' => (string) $paymentMethod->id,
            'payment_type' => 'paid',
            'status' => 'completed',
            'payment_status' => 'paid',
            'order_discount' => 5,
            'tax_amount' => 10,
            'amount_paid' => 150,
            'total_amount' => 200,
            'discount_amount' => 10,
            'grand_total' => 200,
            'amount_due' => 50,
            'items' => [
                [
                    'product_item_id' => (string) $productItem->id,
                    'quantity' => 2,
                    'unit_price' => 100,
                    'discount_amount' => 5,
                    'total_amount' => 195,
                ],
            ],
        ];

        $component = Livewire::test(CreateSale::class);

        $component->set('data', $formData);

        $component
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $sale = Sale::where('customer_id', $customer->id)->first();

        $this->assertNotNull($sale);
        $this->assertEquals('completed', $sale->status);
        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals(200.0, (float) $sale->grand_total);
        $this->assertEquals(50.0, (float) $sale->amount_due);

        $this->assertDatabaseHas(SaleItem::class, [
            'sale_id' => $sale->id,
            'product_item_id' => $productItem->id,
            'quantity' => 2,
        ]);
    }

    public function test_can_update_sale(): void
    {
        $sale = Sale::factory()
            ->has(SaleItem::factory(), 'items')
            ->create([
                'status' => 'draft',
                'payment_status' => 'pending',
                'payment_type' => 'paid',
            ]);
        $existingItem = $sale->items()->first();

        $newCustomer = Customer::factory()->create();
        $newPaymentMethod = PaymentMethod::factory()->create();
        $newProductItem = ProductItem::factory()->create();

        $updateData = [
            'customer_id' => (string) $newCustomer->id,
            'payment_method_id' => (string) $newPaymentMethod->id,
            'payment_type' => 'credit',
            'status' => 'completed',
            'payment_status' => 'partial',
            'order_discount' => 10,
            'tax_amount' => 15,
            'amount_paid' => 75,
            'total_amount' => 240,
            'discount_amount' => 15,
            'grand_total' => 240,
            'amount_due' => 165,
            'items' => [
                [
                    'id' => $existingItem?->getKey(),
                    'product_item_id' => (string) $newProductItem->id,
                    'quantity' => 3,
                    'unit_price' => 80,
                    'discount_amount' => 5,
                    'total_amount' => 235,
                ],
            ],
        ];

        $component = Livewire::test(EditSale::class, ['record' => $sale->getKey()]);

        $component->set('data', $updateData);

        $component
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $sale->refresh();

        $this->assertEquals($newCustomer->id, $sale->customer_id);
        $this->assertEquals($newPaymentMethod->id, $sale->payment_method_id);
        $this->assertEquals('credit', $sale->payment_type);
        $this->assertEqualsWithDelta(240.0, (float) $sale->grand_total, 0.01);
        $this->assertEqualsWithDelta(165.0, (float) $sale->amount_due, 0.01);

        $this->assertDatabaseHas(SaleItem::class, [
            'sale_id' => $sale->id,
            'product_item_id' => $newProductItem->id,
            'quantity' => 3,
        ]);
    }

    public function test_can_delete_sale(): void
    {
        $sale = Sale::factory()
            ->has(SaleItem::factory(), 'items')
            ->create();

        Livewire::test(ListSales::class)
            ->callTableAction(DeleteAction::class, $sale);

        $this->assertModelMissing($sale);
        $this->assertDatabaseMissing(SaleItem::class, [
            'sale_id' => $sale->id,
        ]);
    }
}
