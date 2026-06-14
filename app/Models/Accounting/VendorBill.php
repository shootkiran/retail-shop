<?php

namespace App\Models\Accounting;

use App\Models\BusinessSetting;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Vendor;
use App\Services\Accounting\JournalEntryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $business_id
 * @property int $vendor_id
 * @property string $bill_date
 * @property string|null $due_date
 * @property string $reference
 * @property string $status
 * @property float|string $total_amount
 * @property float|string $discount_amount
 * @property float|string $tax_amount
 * @property float|string $grand_total
 * @property float|string $amount_paid
 * @property float|string $amount_due
 * @property string|null $notes
 */
class VendorBill extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'vendor_id',
        'bill_date',
        'due_date',
        'reference',
        'status',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'amount_paid',
        'amount_due',
        'notes',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (VendorBill $bill): void {
            $bill->checkTransactionPeriodLock('bill_date');
        });

        static::deleting(function (VendorBill $bill): void {
            $bill->checkTransactionPeriodLock('bill_date', true);
        });

        static::creating(function (VendorBill $bill): void {
            if (blank($bill->reference)) {
                $bill->reference = 'BILL-'.Str::upper(Str::ulid());
            }
        });

        static::saved(function (VendorBill $bill): void {
            $bill->refreshTotals();
            if ($bill->status === 'posted' || $bill->status === 'paid' || $bill->status === 'partially_paid') {
                $bill->syncJournalEntry();
            } else {
                // Delete journal entry if draft or voided
                JournalEntry::withoutGlobalScopes()
                    ->where('source_type', $bill->getMorphClass())
                    ->where('source_id', $bill->getKey())
                    ->delete();
            }
        });

        static::deleted(function (VendorBill $bill): void {
            JournalEntry::withoutGlobalScopes()
                ->where('source_type', $bill->getMorphClass())
                ->where('source_id', $bill->getKey())
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

    /** @return HasMany<VendorBillItem, self> */
    public function items(): HasMany
    {
        return $this->hasMany(VendorBillItem::class);
    }

    /** @return HasMany<VendorBillPayment, self> */
    public function payments(): HasMany
    {
        return $this->hasMany(VendorBillPayment::class);
    }

    public function refreshTotals(): void
    {
        if (! $this->exists) {
            return;
        }

        $this->loadMissing(['items', 'payments']);

        $lineTotal = $this->items->sum(fn (VendorBillItem $item) => $item->quantity * $item->unit_cost);
        $taxTotal = $this->items->sum('tax_amount');
        $grandTotal = max($lineTotal - (float) $this->discount_amount + $taxTotal, 0.00);

        $amountPaid = $this->payments->sum('amount');
        $amountDue = max($grandTotal - $amountPaid, 0.00);

        $status = $this->status;
        if ($status !== 'void' && $status !== 'draft') {
            if ($amountDue <= 0) {
                $status = 'paid';
            } elseif ($amountPaid > 0) {
                $status = 'partially_paid';
            } else {
                $status = 'posted';
            }
        }

        $this->forceFill([
            'total_amount' => $lineTotal,
            'tax_amount' => $taxTotal,
            'grand_total' => $grandTotal,
            'amount_paid' => $amountPaid,
            'amount_due' => $amountDue,
            'status' => $status,
        ]);

        if ($this->isDirty(['total_amount', 'tax_amount', 'grand_total', 'amount_paid', 'amount_due', 'status'])) {
            $this->saveQuietly();
        }
    }

    public function syncJournalEntry(): void
    {
        $business = $this->business;
        if (! $business || ! $this->exists) {
            return;
        }

        $service = app(JournalEntryService::class);

        // Accounts
        $apAccount = $service->getOrCreateAccount($business, 'liability', 'Accounts Payable', '2010', 'Accounts Payable', 'Vendor outstanding balances.');
        $inventoryAccount = $service->getOrCreateAccount($business, 'asset', 'Inventory', '1210', 'Merchandise Inventory', 'Goods purchased for resale.');
        $discountAccount = $service->getOrCreateAccount($business, 'expense', 'Cost of Goods Sold', '5020', 'Purchase Discounts', 'Discounts received from vendors.');
        $taxInputAccount = $service->getOrCreateAccount($business, 'asset', 'Prepaid and Deferred Charges', '1320', 'Purchase Tax Paid', 'Tax paid on purchases.');

        $lines = [];

        // Debit: Inventory
        $grossCost = $this->items->isEmpty() ? (float) $this->total_amount : $this->items->sum(fn ($item) => $item->quantity * $item->unit_cost);
        if ($grossCost > 0) {
            $lines[] = [
                'account_id' => $inventoryAccount->id,
                'debit' => (float) $grossCost,
                'credit' => 0.00,
                'notes' => 'Inventory increase from Bill '.$this->reference,
            ];
        }

        // Debit: Tax Input
        if ((float) $this->tax_amount > 0) {
            $lines[] = [
                'account_id' => $taxInputAccount->id,
                'debit' => (float) $this->tax_amount,
                'credit' => 0.00,
                'notes' => 'Tax input credit from Bill '.$this->reference,
            ];
        }

        // Credit: Accounts Payable
        if ((float) $this->grand_total > 0) {
            $lines[] = [
                'account_id' => $apAccount->id,
                'debit' => 0.00,
                'credit' => (float) $this->grand_total + (float) $this->discount_amount - (float) $this->tax_amount, // should equal grossCost? Yes
                'notes' => 'Liability recorded for Bill '.$this->reference,
            ];
        }

        // Credit: Purchase Discounts
        if ((float) $this->discount_amount > 0) {
            $lines[] = [
                'account_id' => $discountAccount->id,
                'debit' => 0.00,
                'credit' => (float) $this->discount_amount,
                'notes' => 'Discount on Bill '.$this->reference,
            ];
        }

        // Fix AP entry value to match accounts payable liability (equals grand_total)
        foreach ($lines as &$line) {
            if ($line['account_id'] === $apAccount->id) {
                $line['credit'] = (float) $this->grand_total;
                break;
            }
        }

        // Balance check
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
                $this->bill_date ?? now(),
                $this->reference,
                'Vendor Bill booking '.$this->reference,
                $lines,
                $this
            );
        }
    }
}
