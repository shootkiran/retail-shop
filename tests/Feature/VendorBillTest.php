<?php

namespace Tests\Feature;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\VendorBill;
use App\Models\Accounting\VendorBillItem;
use App\Models\Accounting\VendorBillPayment;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\ProductItem;
use App\Models\Vendor;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorBillTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;
    protected Vendor $vendor;
    protected ProductItem $product;

    protected function setUp(): void
    {
        parent::setUp();

        Business::query()->delete();

        $this->business = Business::create([
            'name' => 'AP Test Business',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $this->business->refresh();
        app(CurrentBusiness::class)->clear();

        $this->vendor = Vendor::create([
            'business_id' => $this->business->id,
            'name' => 'Supplier Inc',
            'email' => 'supplier@example.com',
            'phone' => '9800000000',
            'contact_person' => 'Supplier Support',
            'address' => 'Supplier street 1',
        ]);

        $this->product = ProductItem::create([
            'business_id' => $this->business->id,
            'name' => 'Inventory Item',
            'sku' => 'ITEM-001',
            'barcode' => '1234567890123',
            'unit_cost' => 50,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
    }

    public function test_creating_vendor_bill_with_status_draft_does_not_generate_journal_entry(): void
    {
        $bill = VendorBill::create([
            'business_id' => $this->business->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'draft',
            'bill_date' => '2026-06-13',
            'due_date' => '2026-06-23',
        ]);

        VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'product_item_id' => $this->product->id,
            'quantity' => 2,
            'unit_cost' => 50,
            'tax_amount' => 5,
        ]);

        $bill->refresh();

        $journalEntry = JournalEntry::where('source_type', $bill->getMorphClass())
            ->where('source_id', $bill->id)
            ->first();

        $this->assertNull($journalEntry);
    }

    public function test_posting_vendor_bill_creates_balanced_journal_entry(): void
    {
        $bill = VendorBill::create([
            'business_id' => $this->business->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'posted',
            'bill_date' => '2026-06-13',
            'due_date' => '2026-06-23',
        ]);

        VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'product_item_id' => $this->product->id,
            'quantity' => 2,
            'unit_cost' => 50,
            'tax_amount' => 13, // 13% tax
        ]);

        $bill->refresh();

        $this->assertEquals('posted', $bill->status);
        $this->assertEquals(100.00, (float) $bill->total_amount);
        $this->assertEquals(13.00, (float) $bill->tax_amount);
        $this->assertEquals(113.00, (float) $bill->grand_total);
        $this->assertEquals(113.00, (float) $bill->amount_due);
        $this->assertEquals(0.00, (float) $bill->amount_paid);

        $journalEntry = JournalEntry::where('source_type', $bill->getMorphClass())
            ->where('source_id', $bill->id)
            ->first();

        $this->assertNotNull($journalEntry);

        // Verify debits equal credits
        $totalDebits = (float) $journalEntry->lines()->sum('debit');
        $totalCredits = (float) $journalEntry->lines()->sum('credit');
        $this->assertEquals($totalDebits, $totalCredits);
        $this->assertEquals(113.00, $totalDebits);

        // Verify lines: debiting Inventory `1210` / Purchase Tax Paid `1320` and crediting Accounts Payable `2010`
        $inventoryLine = $journalEntry->lines()->whereHas('account', function ($q) {
            $q->where('code', '1210');
        })->first();
        $taxLine = $journalEntry->lines()->whereHas('account', function ($q) {
            $q->where('code', '1320');
        })->first();
        $apLine = $journalEntry->lines()->whereHas('account', function ($q) {
            $q->where('code', '2010');
        })->first();

        $this->assertNotNull($inventoryLine);
        $this->assertEquals(100.00, (float) $inventoryLine->debit);
        $this->assertEquals(0.00, (float) $inventoryLine->credit);

        $this->assertNotNull($taxLine);
        $this->assertEquals(13.00, (float) $taxLine->debit);
        $this->assertEquals(0.00, (float) $taxLine->credit);

        $this->assertNotNull($apLine);
        $this->assertEquals(0.00, (float) $apLine->debit);
        $this->assertEquals(113.00, (float) $apLine->credit);
    }

    public function test_recording_bill_payment_partial_and_full_creates_balanced_journal_entry(): void
    {
        $bill = VendorBill::create([
            'business_id' => $this->business->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'posted',
            'bill_date' => '2026-06-13',
            'due_date' => '2026-06-23',
        ]);

        VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'product_item_id' => $this->product->id,
            'quantity' => 2,
            'unit_cost' => 50,
            'tax_amount' => 13,
        ]);

        $bill->refresh();

        // Let's create a bank account for payment
        $bankAccount = BankAccount::create([
            'business_id' => $this->business->id,
            'name' => 'Company Checking Account',
            'bank_name' => 'National Bank',
            'account_number' => '987654321',
            'account_type' => 'checking',
            'opening_balance' => 1000,
            'is_active' => true,
        ]);

        // 1. Record Partial Payment: 50.00
        $payment1 = VendorBillPayment::create([
            'business_id' => $this->business->id,
            'vendor_bill_id' => $bill->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 50.00,
            'payment_date' => '2026-06-14',
            'notes' => 'Partial payment',
        ]);

        $bill->refresh();
        $this->assertEquals('partially_paid', $bill->status);
        $this->assertEquals(50.00, (float) $bill->amount_paid);
        $this->assertEquals(63.00, (float) $bill->amount_due);

        // Verify balanced journal entry for partial payment
        $payment1Entry = JournalEntry::where('source_type', $payment1->getMorphClass())
            ->where('source_id', $payment1->id)
            ->first();

        $this->assertNotNull($payment1Entry);
        $this->assertEquals((float) $payment1Entry->lines()->sum('debit'), (float) $payment1Entry->lines()->sum('credit'));
        $this->assertEquals(50.00, (float) $payment1Entry->lines()->sum('debit'));

        // debiting Accounts Payable `2010`, crediting cash/bank
        $apLine1 = $payment1Entry->lines()->whereHas('account', function ($q) {
            $q->where('code', '2010');
        })->first();
        $bankLine1 = $payment1Entry->lines()->whereHas('account', function ($q) use ($bankAccount) {
            $q->where('id', $bankAccount->account_id);
        })->first();

        $this->assertNotNull($apLine1);
        $this->assertEquals(50.00, (float) $apLine1->debit);
        $this->assertEquals(0.00, (float) $apLine1->credit);

        $this->assertNotNull($bankLine1);
        $this->assertEquals(0.00, (float) $bankLine1->debit);
        $this->assertEquals(50.00, (float) $bankLine1->credit);

        // 2. Record Full Payment (remaining 63.00)
        $payment2 = VendorBillPayment::create([
            'business_id' => $this->business->id,
            'vendor_bill_id' => $bill->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 63.00,
            'payment_date' => '2026-06-15',
            'notes' => 'Full payment',
        ]);

        $bill->refresh();
        $this->assertEquals('paid', $bill->status);
        $this->assertEquals(113.00, (float) $bill->amount_paid);
        $this->assertEquals(0.00, (float) $bill->amount_due);

        // Verify balanced journal entry for full payment
        $payment2Entry = JournalEntry::where('source_type', $payment2->getMorphClass())
            ->where('source_id', $payment2->id)
            ->first();

        $this->assertNotNull($payment2Entry);
        $this->assertEquals((float) $payment2Entry->lines()->sum('debit'), (float) $payment2Entry->lines()->sum('credit'));
        $this->assertEquals(63.00, (float) $payment2Entry->lines()->sum('debit'));

        // debiting Accounts Payable `2010`, crediting cash/bank
        $apLine2 = $payment2Entry->lines()->whereHas('account', function ($q) {
            $q->where('code', '2010');
        })->first();
        $bankLine2 = $payment2Entry->lines()->whereHas('account', function ($q) use ($bankAccount) {
            $q->where('id', $bankAccount->account_id);
        })->first();

        $this->assertNotNull($apLine2);
        $this->assertEquals(63.00, (float) $apLine2->debit);
        $this->assertEquals(0.00, (float) $apLine2->credit);

        $this->assertNotNull($bankLine2);
        $this->assertEquals(0.00, (float) $bankLine2->debit);
        $this->assertEquals(63.00, (float) $bankLine2->credit);
    }
}
