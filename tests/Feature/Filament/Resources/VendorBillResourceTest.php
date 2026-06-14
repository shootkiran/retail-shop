<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\VendorBillResource\Pages\CreateVendorBill;
use App\Filament\Resources\VendorBillResource\Pages\EditVendorBill;
use App\Filament\Resources\VendorBillResource\Pages\ListVendorBills;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\VendorBill;
use App\Models\Accounting\VendorBillPayment;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\CashRegister;
use App\Models\ProductItem;
use App\Models\Vendor;
use App\Support\CurrentBusiness;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class VendorBillResourceTest extends FilamentTestCase
{
    private Business $business;

    protected function setupBusiness(): Business
    {
        // Delete any existing businesses to avoid tenant scoping mismatches in testing
        Business::query()->delete();

        $this->business = Business::create([
            'name' => 'AP Test Business',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $this->user->forceFill([
            'current_business_id' => $this->business->id,
            'office_type' => 'back_office',
            'is_active' => true,
        ])->save();

        $this->user->businesses()->attach($this->business->id, [
            'role' => 'admin',
            'office_type' => 'back_office',
            'is_active' => true,
        ]);

        app(CurrentBusiness::class)->clear();

        return $this->business;
    }

    private function getSettings(): BusinessSetting
    {
        return BusinessSetting::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->first() ?? BusinessSetting::create([
                'business_id' => $this->business->id,
                'country' => 'Nepal',
                'timezone' => 'Asia/Kathmandu',
                'currency_code' => 'NPR',
                'currency_symbol' => 'रू',
            ]);
    }

    public function test_create_and_post_vendor_bill(): void
    {
        $business = $this->setupBusiness();

        $vendor = Vendor::create([
            'name' => 'Supplier A',
            'email' => 'supplier_a@example.com',
            'phone' => '9800000002',
            'contact_person' => 'Sales',
            'address' => 'Kathmandu',
        ]);

        $product = ProductItem::create([
            'business_id' => $business->id,
            'name' => 'Inventory Product',
            'sku' => 'PROD-V3',
            'barcode' => '1234567890128',
            'unit_cost' => 50,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $formData = [
            'vendor_id' => (string) $vendor->id,
            'reference' => 'BILL-DRAFT1',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'draft',
            'notes' => 'Draft bill notes',
            'total_amount' => 100.00,
            'discount_amount' => 10.00,
            'tax_amount' => 13.00,
            'grand_total' => 103.00,
            'amount_paid' => 0.00,
            'amount_due' => 103.00,
            'items' => [
                [
                    'product_item_id' => (string) $product->id,
                    'quantity' => 2,
                    'unit_cost' => 50.00,
                    'tax_amount' => 13.00,
                    'total_amount' => 113.00,
                ],
            ],
        ];

        // Create in draft status
        Livewire::test(CreateVendorBill::class)
            ->set('data', $formData)
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $bill = VendorBill::where('reference', 'BILL-DRAFT1')->first();
        $this->assertNotNull($bill);
        $this->assertEquals('draft', $bill->status);

        // Verify no journal entry is created for draft status
        $je = JournalEntry::where('source_type', $bill->getMorphClass())
            ->where('source_id', $bill->id)
            ->first();
        $this->assertNull($je);

        // Transition to posted status
        Livewire::test(EditVendorBill::class, ['record' => $bill->getKey()])
            ->set('data.status', 'posted')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $bill->refresh();
        $this->assertEquals('posted', $bill->status);

        // Verify journal entry created: Debit Inventory 1210 / Debit Purchase Tax Paid 1320, Credit Accounts Payable 2010
        $je = JournalEntry::where('source_type', $bill->getMorphClass())
            ->where('source_id', $bill->id)
            ->first();
        $this->assertNotNull($je);

        $lines = $je->lines()->with('account')->get();
        // check Debit Inventory 1210: 100.00
        $this->assertTrue($lines->contains(fn ($line) => $line->account->code === '1210' && (float)$line->debit === 100.00));
        // check Debit Purchase Tax Paid 1320: 13.00
        $this->assertTrue($lines->contains(fn ($line) => $line->account->code === '1320' && (float)$line->debit === 13.00));
        // check Credit Accounts Payable 2010: 103.00
        $this->assertTrue($lines->contains(fn ($line) => $line->account->code === '2010' && (float)$line->credit === 103.00));
    }

    public function test_record_payment_and_ledger_verification(): void
    {
        $business = $this->setupBusiness();

        $vendor = Vendor::create([
            'name' => 'Supplier B',
            'email' => 'supplier_b@example.com',
            'phone' => '9800000003',
            'contact_person' => 'Sales',
            'address' => 'Kathmandu',
        ]);

        $bill = VendorBill::create([
            'business_id' => $business->id,
            'vendor_id' => $vendor->id,
            'reference' => 'BILL-POSTED1',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'posted',
            'total_amount' => 100.00,
            'discount_amount' => 10.00,
            'tax_amount' => 13.00,
            'grand_total' => 103.00,
            'amount_paid' => 0.00,
            'amount_due' => 103.00,
        ]);

        // Create product item and bill item to ensure items relationship is populated
        $product = ProductItem::create([
            'business_id' => $business->id,
            'name' => 'Inventory Product 2',
            'sku' => 'PROD-V4',
            'barcode' => '1234567890129',
            'unit_cost' => 50,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        \App\Models\Accounting\VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'product_item_id' => $product->id,
            'quantity' => 2,
            'unit_cost' => 50.00,
            'tax_amount' => 13.00,
            'total_amount' => 113.00,
        ]);

        // Sync journal entry for the bill to set up initial accounts/entries
        $bill->syncJournalEntry();

        $bankAccount = BankAccount::create([
            'business_id' => $business->id,
            'name' => 'Operating Bank',
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'account_type' => 'checking',
            'opening_balance' => 1000,
            'is_active' => true,
        ]);

        // Part 1: Record a partial payment (40.00)
        Livewire::test(ListVendorBills::class)
            ->callTableAction('recordPayment', $bill, data: [
                'payment_date' => now()->toDateString(),
                'account_type' => BankAccount::class,
                'account_id' => $bankAccount->id,
                'amount' => 40.00,
                'reference' => 'VBP-PAY1',
                'notes' => 'Partial payment',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $payment = VendorBillPayment::where('reference', 'VBP-PAY1')->first();
        $this->assertNotNull($payment);
        $this->assertEquals(40.00, (float)$payment->amount);

        // Verify parent bill totals updated
        $bill->refresh();
        $this->assertEquals('partially_paid', $bill->status);
        $this->assertEquals(40.00, (float)$bill->amount_paid);
        $this->assertEquals(63.00, (float)$bill->amount_due);

        // Verify payment journal entry (debit Accounts Payable 2010, credit cash/bank account)
        $paymentJe = JournalEntry::where('source_type', $payment->getMorphClass())
            ->where('source_id', $payment->id)
            ->first();
        $this->assertNotNull($paymentJe);

        $paymentLines = $paymentJe->lines()->with('account')->get();
        // Credit bank:
        $this->assertTrue($paymentLines->contains(fn ($line) => $line->account_id === $bankAccount->account_id && (float)$line->credit === 40.00));
        // Debit AP (2010):
        $this->assertTrue($paymentLines->contains(fn ($line) => $line->account->code === '2010' && (float)$line->debit === 40.00));

        // Part 2: Record remaining/full payment (63.00)
        Livewire::test(ListVendorBills::class)
            ->callTableAction('recordPayment', $bill, data: [
                'payment_date' => now()->toDateString(),
                'account_type' => BankAccount::class,
                'account_id' => $bankAccount->id,
                'amount' => 63.00,
                'reference' => 'VBP-PAY2',
                'notes' => 'Remaining payment',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $bill->refresh();
        $this->assertEquals('paid', $bill->status);
        $this->assertEquals(103.00, (float)$bill->amount_paid);
        $this->assertEquals(0.00, (float)$bill->amount_due);
    }

    public function test_edit_and_delete_payment(): void
    {
        $business = $this->setupBusiness();

        $vendor = Vendor::create([
            'name' => 'Supplier C',
            'email' => 'supplier_c@example.com',
            'phone' => '9800000004',
            'contact_person' => 'Sales',
            'address' => 'Kathmandu',
        ]);

        $bill = VendorBill::create([
            'business_id' => $business->id,
            'vendor_id' => $vendor->id,
            'reference' => 'BILL-POSTED2',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'posted',
            'total_amount' => 100.00,
            'discount_amount' => 10.00,
            'tax_amount' => 13.00,
            'grand_total' => 103.00,
            'amount_paid' => 0.00,
            'amount_due' => 103.00,
        ]);

        // Create product item and bill item to ensure items relationship is populated
        $product = ProductItem::create([
            'business_id' => $business->id,
            'name' => 'Inventory Product 3',
            'sku' => 'PROD-V5',
            'barcode' => '1234567890130',
            'unit_cost' => 50,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        \App\Models\Accounting\VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'product_item_id' => $product->id,
            'quantity' => 2,
            'unit_cost' => 50.00,
            'tax_amount' => 13.00,
            'total_amount' => 113.00,
        ]);

        $bill->syncJournalEntry();

        $bankAccount = BankAccount::create([
            'business_id' => $business->id,
            'name' => 'Operating Bank',
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'account_type' => 'checking',
            'opening_balance' => 1000,
            'is_active' => true,
        ]);

        $cashRegister = CashRegister::create([
            'business_id' => $business->id,
            'name' => 'Main Cash Register',
            'opening_balance' => 500,
            'is_active' => true,
        ]);

        // Create a payment
        $payment = VendorBillPayment::create([
            'business_id' => $business->id,
            'vendor_bill_id' => $bill->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 40.00,
            'payment_date' => now()->toDateString(),
            'reference' => 'VBP-EDIT1',
            'notes' => 'Initial payment',
        ]);

        // Refresh and verify bill totals
        $bill->refresh();
        $this->assertEquals(40.00, (float)$bill->amount_paid);

        // Edit/update the payment: change payment amount to 50.00 and shift to CashRegister
        $payment->update([
            'amount' => 50.00,
            'bank_account_id' => null,
            'cash_register_id' => $cashRegister->id,
        ]);

        // Verify parent bill totals updated
        $bill->refresh();
        $this->assertEquals(50.00, (float)$bill->amount_paid);

        // Verify the payment journal entry is updated (with cashRegister's account and 50.00 amount)
        $paymentJe = JournalEntry::where('source_type', $payment->getMorphClass())
            ->where('source_id', $payment->id)
            ->first();
        $this->assertNotNull($paymentJe);

        $paymentLines = $paymentJe->lines()->with('account')->get();
        // Credit cash register:
        $this->assertTrue($paymentLines->contains(fn ($line) => $line->account_id === $cashRegister->account_id && (float)$line->credit === 50.00));
        // Debit AP:
        $this->assertTrue($paymentLines->contains(fn ($line) => $line->account->code === '2010' && (float)$line->debit === 50.00));

        // Delete the payment
        $paymentId = $payment->id;
        $paymentType = $payment->getMorphClass();
        $payment->delete();

        // Verify parent bill totals updated (paid is 0.00)
        $bill->refresh();
        $this->assertEquals(0.00, (float)$bill->amount_paid);

        // Verify the payment journal entry is deleted
        $this->assertDatabaseMissing(JournalEntry::class, [
            'source_type' => $paymentType,
            'source_id' => $paymentId,
        ]);
    }

    public function test_period_locking_controls_respected(): void
    {
        $business = $this->setupBusiness();

        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        $vendor = Vendor::create([
            'name' => 'Supplier D',
            'email' => 'supplier_d@example.com',
            'phone' => '9800000005',
            'contact_person' => 'Sales',
            'address' => 'Kathmandu',
        ]);

        // 1. Create a bill on or before the lock date (should fail)
        try {
            VendorBill::create([
                'business_id' => $business->id,
                'vendor_id' => $vendor->id,
                'reference' => 'BILL-LOCKED-CREATE',
                'bill_date' => '2026-06-01',
                'due_date' => '2026-06-11',
                'status' => 'draft',
            ]);
            $this->fail('Expected RuntimeException when creating bill on or before lock date.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // 2. Create a bill after the lock date (should succeed)
        $bill = VendorBill::create([
            'business_id' => $business->id,
            'vendor_id' => $vendor->id,
            'reference' => 'BILL-UNLOCKED',
            'bill_date' => '2026-06-02',
            'due_date' => '2026-06-12',
            'status' => 'draft',
        ]);
        $this->assertNotNull($bill);

        // 3. Update the bill's date to on or before the lock date (should fail)
        try {
            $bill->update([
                'bill_date' => '2026-06-01',
            ]);
            $this->fail('Expected RuntimeException when updating bill date to on or before lock date.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // 4. Update the bill after the lock date (should succeed)
        $bill->update([
            'notes' => 'Updated bill notes after lock date',
        ]);

        // Create a payment after the lock date
        $bankAccount = BankAccount::create([
            'business_id' => $business->id,
            'name' => 'Operating Bank',
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'account_type' => 'checking',
            'opening_balance' => 1000,
            'is_active' => true,
        ]);

        // 5. Create a payment on or before the lock date (should fail)
        try {
            VendorBillPayment::create([
                'business_id' => $business->id,
                'vendor_bill_id' => $bill->id,
                'bank_account_id' => $bankAccount->id,
                'amount' => 10.00,
                'payment_date' => '2026-06-01',
            ]);
            $this->fail('Expected RuntimeException when creating payment on or before lock date.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // 6. Create a payment after the lock date (should succeed)
        $payment = VendorBillPayment::create([
            'business_id' => $business->id,
            'vendor_bill_id' => $bill->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 10.00,
            'payment_date' => '2026-06-02',
        ]);
        $this->assertNotNull($payment);

        // 7. Update the payment's date to on or before the lock date (should fail)
        try {
            $payment->update([
                'payment_date' => '2026-06-01',
            ]);
            $this->fail('Expected RuntimeException when updating payment date to on or before lock date.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // 8. Delete the payment after changing the lock date to block it (setting lock date after payment date)
        $settings->update([
            'period_lock_date' => '2026-06-03',
        ]);

        try {
            $payment->delete();
            $this->fail('Expected RuntimeException when deleting payment on or before lock date.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // 9. Delete the bill on or before the lock date (should fail)
        try {
            $bill->delete();
            $this->fail('Expected RuntimeException when deleting bill on or before lock date.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }
    }
}
