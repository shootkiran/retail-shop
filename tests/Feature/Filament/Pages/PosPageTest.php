<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\POS;
use App\Models\Business;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductItem;
use App\Models\Sale;
use App\Models\User;
use App\Support\CurrentBusiness;
use Tests\Feature\Filament\FilamentTestCase;

class PosPageTest extends FilamentTestCase
{
    protected Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create([
            'name' => 'Retail Shop',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $this->user->forceFill([
            'current_business_id' => $this->business->id,
            'office_type' => 'front_office',
            'is_active' => true,
        ])->save();

        $this->user->businesses()->attach($this->business->id, [
            'role' => 'cashier',
            'office_type' => 'front_office',
            'is_active' => true,
        ]);

        app(CurrentBusiness::class)->clear();
    }

    public function test_checkout_updates_stock_and_customer_balance_for_credit_sales(): void
    {
        $paymentMethod = PaymentMethod::create([
            'name' => 'Cash',
            'type' => 'cash',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Credit Customer',
            'email' => 'credit@example.com',
            'phone' => '9800000001',
            'credit_limit' => 1000,
            'outstanding_balance' => 125,
        ]);

        $product = ProductItem::create([
            'product_category_id' => null,
            'vendor_id' => null,
            'name' => 'POS Product',
            'sku' => 'POS-001',
            'barcode' => '9876543210987',
            'unit_cost' => 60,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 5,
            'reorder_level' => 1,
            'is_active' => true,
        ]);

        $terminal = $this->business->terminals()->first();

        $pos = app(POS::class);
        $pos->mount();
        $pos->customerId = $customer->id;
        $pos->paymentMethodId = $paymentMethod->id;
        $pos->paymentType = 'credit';
        $pos->amountPaid = 0;
        $pos->orderDiscount = 0;
        $pos->posTerminalId = $terminal?->id;
        $pos->cart = [
            (string) $product->id => [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'unit_price' => 100,
                'tax_rate' => 0,
                'quantity' => 3,
                'discount' => 0,
            ],
        ];

        $sale = $pos->checkout();

        $this->assertInstanceOf(Sale::class, $sale);
        $this->assertSame('pending', $sale->payment_status);
        $this->assertSame('credit', $sale->payment_type);
        $this->assertSame('300.00', $sale->grand_total);
        $this->assertSame('300.00', $sale->amount_due);

        $this->assertDatabaseHas(ProductItem::class, [
            'id' => $product->id,
            'stock_quantity' => 2,
        ]);

        $this->assertDatabaseHas(Customer::class, [
            'id' => $customer->id,
            'outstanding_balance' => 425,
        ]);
    }

    public function test_checkout_rolls_back_when_stock_is_insufficient(): void
    {
        $paymentMethod = PaymentMethod::create([
            'name' => 'Cash',
            'type' => 'cash',
            'is_active' => true,
        ]);

        $product = ProductItem::create([
            'product_category_id' => null,
            'vendor_id' => null,
            'name' => 'Limited Stock Product',
            'sku' => 'POS-002',
            'barcode' => '9876543210123',
            'unit_cost' => 60,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 2,
            'reorder_level' => 1,
            'is_active' => true,
        ]);

        $terminal = $this->business->terminals()->first();

        $pos = app(POS::class);
        $pos->mount();
        $pos->paymentMethodId = $paymentMethod->id;
        $pos->paymentType = 'paid';
        $pos->amountPaid = 100;
        $pos->orderDiscount = 0;
        $pos->posTerminalId = $terminal?->id;
        $pos->cart = [
            (string) $product->id => [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'unit_price' => 100,
                'tax_rate' => 0,
                'quantity' => 3,
                'discount' => 0,
            ],
        ];

        $sale = $pos->checkout();

        $this->assertNull($sale);
        $this->assertDatabaseMissing(Sale::class, [
            'payment_method_id' => $paymentMethod->id,
            'total_amount' => 300,
        ]);
        $this->assertDatabaseHas(ProductItem::class, [
            'id' => $product->id,
            'stock_quantity' => 2,
        ]);
    }
}
