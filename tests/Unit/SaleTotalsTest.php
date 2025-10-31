<?php

namespace Tests\Unit;

use App\Models\ProductItem;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_discount_is_included_in_totals(): void
    {
        $product = ProductItem::create([
            'name' => 'Test Product',
            'sku' => 'TP-001',
            'barcode' => '1234567890',
            'unit_price' => 1000,
            'unit_cost' => 500,
            'stock_quantity' => 10,
            'reorder_level' => 1,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'status' => 'completed',
            'payment_status' => 'pending',
            'payment_type' => 'paid',
            'order_discount' => 500,
            'tax_amount' => 100,
            'amount_paid' => 200,
        ]);

        $sale->items()->create([
            'product_item_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 1000,
            'discount_amount' => 100,
        ]);

        $sale->refresh();

        $this->assertSame('2000.00', $sale->total_amount);
        $this->assertSame('600.00', $sale->discount_amount);
        $this->assertSame('1500.00', $sale->grand_total);
        $this->assertSame('1300.00', $sale->amount_due);
    }
}
