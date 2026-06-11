<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductItem;
use App\Models\Sale;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleInvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_is_forbidden_for_guest_users(): void
    {
        $sale = Sale::factory()->create();

        $this->get(route('sales.invoice', $sale))
            ->assertForbidden();
    }

    public function test_invoice_is_scoped_to_the_current_business(): void
    {
        $business = Business::create([
            'name' => 'Acme Retail',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'current_business_id' => $business->id,
            'office_type' => 'back_office',
            'is_active' => true,
        ]);

        $user->businesses()->attach($business->id, [
            'role' => 'admin',
            'office_type' => 'back_office',
            'is_active' => true,
        ]);

        $this->actingAs($user);
        app(CurrentBusiness::class)->clear();

        $customer = Customer::create([
            'name' => 'Walk-in Customer',
            'email' => 'customer@example.com',
            'phone' => '9800000000',
            'credit_limit' => 1000,
            'outstanding_balance' => 0,
        ]);

        $paymentMethod = PaymentMethod::create([
            'name' => 'Cash',
            'type' => 'cash',
            'is_active' => true,
        ]);

        $product = ProductItem::create([
            'product_category_id' => null,
            'vendor_id' => null,
            'name' => 'Invoice Product',
            'sku' => 'INV-001',
            'barcode' => '1234567890123',
            'unit_cost' => 50,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'reorder_level' => 1,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethod->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_type' => 'paid',
            'total_amount' => 100,
            'discount_amount' => 0,
            'order_discount' => 0,
            'tax_amount' => 0,
            'grand_total' => 100,
            'amount_paid' => 100,
            'amount_due' => 0,
            'sold_at' => now(),
        ]);

        $sale->items()->create([
            'product_item_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'discount_amount' => 0,
            'total_amount' => 100,
        ]);

        $this->get(route('sales.invoice', ['sale' => $sale, 'print' => 1]))
            ->assertOk()
            ->assertSee($sale->reference);
    }
}
