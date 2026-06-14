<?php

namespace App\Models;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToBusiness;
use App\Services\Accounting\JournalEntryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Purchase extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'vendor_id',
        'payment_method_id',
        'reference',
        'status',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'amount_paid',
        'amount_due',
        'purchased_at',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'purchased_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Purchase $purchase): void {
            $purchase->checkTransactionPeriodLock('purchased_at');
        });

        static::deleting(function (Purchase $purchase): void {
            $purchase->checkTransactionPeriodLock('purchased_at', true);
        });

        static::creating(function (Purchase $purchase): void {
            if (blank($purchase->reference)) {
                $purchase->reference = 'PO-'.Str::upper(Str::ulid());
            }
        });

        static::saved(function (Purchase $purchase): void {
            $purchase->refreshTotals();
            $purchase->syncPaymentTransaction();
            $purchase->syncJournalEntry();
        });

        static::deleted(function (Purchase $purchase): void {
            FinancialEntry::query()
                ->where('entry_type', 'purchase_payment')
                ->where('reference', $purchase->reference)
                ->delete();

            JournalEntry::withoutGlobalScopes()
                ->where('source_type', $purchase->getMorphClass())
                ->where('source_id', $purchase->getKey())
                ->delete();
        });
    }

    public function checkTransactionPeriodLock(string $dateField, bool $isDeleting = false): void
    {
        $businessId = $this->getOriginal('business_id') ?? $this->business_id;
        if (! $businessId) {
            return;
        }

        $resolvedDateVal = $this->getOriginal($dateField) ?? $this->$dateField;
        if (! $resolvedDateVal) {
            return;
        }

        $businessIds = array_unique(array_filter([
            $this->getOriginal('business_id'),
            $this->business_id,
            $businessId
        ]));

        foreach ($businessIds as $bizId) {
            $lockDateVal = BusinessSetting::withoutGlobalScopes()
                ->where('business_id', $bizId)
                ->value('period_lock_date');
            if ($lockDateVal) {
                $lockDate = Carbon::parse($lockDateVal)->startOfDay();

                $resolvedDate = Carbon::parse($resolvedDateVal)->startOfDay();
                if ($resolvedDate->lessThanOrEqualTo($lockDate)) {
                    throw new \RuntimeException("This transaction falls within a locked fiscal period (Lock Date: {$lockDate->toDateString()}). Modifications are blocked.");
                }

                $currentDateVal = $this->$dateField;
                if ($currentDateVal) {
                    $currentDate = Carbon::parse($currentDateVal)->startOfDay();
                    if ($currentDate->lessThanOrEqualTo($lockDate)) {
                        throw new \RuntimeException("This transaction falls within a locked fiscal period (Lock Date: {$lockDate->toDateString()}). Modifications are blocked.");
                    }
                }
            }
        }
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function refreshTotals(): void
    {
        if (! $this->exists) {
            return;
        }

        $this->loadMissing('items');

        $lineTotal = $this->items->sum(fn (PurchaseItem $item) => $item->quantity * $item->unit_cost);
        $lineDiscount = $this->items->sum('discount_amount');

        $this->forceFill([
            'total_amount' => $lineTotal,
            'discount_amount' => $lineDiscount,
            'grand_total' => max($lineTotal - $lineDiscount + $this->tax_amount, 0),
        ]);

        $this->amount_due = max($this->grand_total - $this->amount_paid, 0);

        if ($this->isDirty(['total_amount', 'discount_amount', 'grand_total', 'amount_due'])) {
            $this->saveQuietly();
        }

        $this->syncJournalEntry();
    }

    public function syncJournalEntry(): void
    {
        $business = $this->business;
        if (! $business || ! $this->exists) {
            return;
        }

        $this->loadMissing(['items.product', 'paymentMethod.settlementAccount']);

        $service = app(JournalEntryService::class);

        // Prepare operational accounts
        $apAccount = $service->getOrCreateAccount($business, 'liability', 'Accounts Payable', '2010', 'Accounts Payable', 'Vendor outstanding balances.');
        $inventoryAccount = $service->getOrCreateAccount($business, 'asset', 'Inventory', '1210', 'Merchandise Inventory', 'Goods purchased for resale.');
        $discountAccount = $service->getOrCreateAccount($business, 'expense', 'Cost of Goods Sold', '5020', 'Purchase Discounts', 'Discounts received from vendors.');
        $taxInputAccount = $service->getOrCreateAccount($business, 'asset', 'Prepaid and Deferred Charges', '1320', 'Purchase Tax Paid', 'Tax paid on purchases.');

        $lines = [];

        // Debit: Inventory (gross cost of items purchased)
        $grossCost = $this->items->sum(fn ($item) => $item->quantity * $item->unit_cost);
        if ($grossCost > 0) {
            $lines[] = [
                'account_id' => $inventoryAccount->id,
                'debit' => (float) $grossCost,
                'credit' => 0.00,
                'notes' => 'Inventory increase from purchase '.$this->reference,
            ];
        }

        // Debit: Input Tax Paid (if any)
        if ((float) $this->tax_amount > 0) {
            $lines[] = [
                'account_id' => $taxInputAccount->id,
                'debit' => (float) $this->tax_amount,
                'credit' => 0.00,
                'notes' => 'Tax paid on purchase '.$this->reference,
            ];
        }

        // Credit: Accounts Payable (for amount due)
        if ((float) $this->amount_due > 0) {
            $lines[] = [
                'account_id' => $apAccount->id,
                'debit' => 0.00,
                'credit' => (float) $this->amount_due,
                'notes' => 'Outstanding payable for purchase '.$this->reference,
            ];
        }

        // Credit: Bank or Cash (for amount paid)
        if ((float) $this->amount_paid > 0) {
            $paymentMethod = $this->paymentMethod;
            if ($paymentMethod) {
                $settlementAccount = $paymentMethod->settlementAccount;
                if ($settlementAccount && $settlementAccount->account_id) {
                    $lines[] = [
                        'account_id' => $settlementAccount->account_id,
                        'debit' => 0.00,
                        'credit' => (float) $this->amount_paid,
                        'notes' => 'Cash/Bank paid for purchase '.$this->reference,
                    ];
                }
            }
        }

        // Credit: Purchase Discounts (if any)
        if ((float) $this->discount_amount > 0) {
            $lines[] = [
                'account_id' => $discountAccount->id,
                'debit' => 0.00,
                'credit' => (float) $this->discount_amount,
                'notes' => 'Discount on purchase '.$this->reference,
            ];
        }

        // Try to balance any minor rounding differences by adjusting Inventory
        $totalDebits = array_sum(array_column($lines, 'debit'));
        $totalCredits = array_sum(array_column($lines, 'credit'));
        $diff = round($totalCredits - $totalDebits, 2);

        if ($diff != 0.00 && count($lines) > 0) {
            foreach ($lines as &$line) {
                if ($line['account_id'] === $inventoryAccount->id) {
                    $line['debit'] = round($line['debit'] + $diff, 2);
                    break;
                }
            }
        }

        if (count($lines) > 0) {
            $service->createEntry(
                $business,
                $this->purchased_at ?? now(),
                $this->reference,
                'Journal entry for Purchase '.$this->reference,
                $lines,
                $this
            );
        }
    }

    protected function syncPaymentTransaction(): void
    {
        if (! $this->exists) {
            return;
        }

        FinancialEntry::query()
            ->where('entry_type', 'purchase_payment')
            ->where('reference', $this->reference)
            ->delete();

        if ((float) $this->amount_paid <= 0) {
            return;
        }

        $this->loadMissing('paymentMethod.settlementAccount');
        $account = $this->paymentMethod?->settlementAccount;

        if (! $account) {
            return;
        }

        FinancialEntry::create([
            'accountable_type' => $account::class,
            'accountable_id' => $account->getKey(),
            'entry_type' => 'purchase_payment',
            'direction' => 'debit',
            'amount' => $this->amount_paid,
            'entry_date' => $this->purchased_at?->toDateString() ?? now()->toDateString(),
            'reference' => $this->reference,
            'notes' => 'Purchase payment recorded from purchase form.',
        ]);
    }
}
