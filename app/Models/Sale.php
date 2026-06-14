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

class Sale extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'customer_id',
        'payment_method_id',
        'pos_terminal_id',
        'reference',
        'status',
        'payment_status',
        'payment_type',
        'delivery_option',
        'delivery_charge',
        'total_amount',
        'discount_amount',
        'order_discount',
        'tax_amount',
        'grand_total',
        'amount_paid',
        'amount_due',
        'notes',
        'sold_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'order_discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'delivery_charge' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'sold_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Sale $sale): void {
            $sale->checkTransactionPeriodLock('sold_at');
        });

        static::deleting(function (Sale $sale): void {
            $sale->checkTransactionPeriodLock('sold_at', true);
        });

        static::creating(function (Sale $sale): void {
            if (blank($sale->reference)) {
                $sale->reference = 'SL-'.Str::upper(Str::ulid());
            }
        });

        static::saved(function (Sale $sale): void {
            $sale->refreshTotals();
            $sale->syncJournalEntry();
        });

        static::deleted(function (Sale $sale): void {
            // Delete journal entry when sale is deleted
            JournalEntry::withoutGlobalScopes()
                ->where('source_type', $sale->getMorphClass())
                ->where('source_id', $sale->getKey())
                ->delete();

            // Also delete COGS entry
            JournalEntry::withoutGlobalScopes()
                ->where('reference', $sale->reference.'-COGS')
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function refreshTotals(): void
    {
        if (! $this->exists) {
            return;
        }

        $this->loadMissing('items');

        $lineTotal = $this->items->sum(fn (SaleItem $item) => $item->quantity * $item->unit_price);
        $lineDiscount = $this->items->sum('discount_amount');
        $orderDiscount = (float) $this->order_discount;
        $deliveryCharge = (float) $this->delivery_charge;
        $totalDiscount = $lineDiscount + $orderDiscount;

        $this->forceFill([
            'total_amount' => $lineTotal,
            'discount_amount' => $totalDiscount,
            'grand_total' => max($lineTotal - $totalDiscount + $this->tax_amount + $deliveryCharge, 0),
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
        $arAccount = $service->getOrCreateAccount($business, 'asset', 'Receivables', '1110', 'Accounts Receivable', 'Customer unpaid invoice balances.');
        $salesRevenueAccount = $service->getOrCreateAccount($business, 'revenue', 'Product Sales', '4010', 'Sales Revenue', 'Primary product sales revenue.');
        $discountAccount = $service->getOrCreateAccount($business, 'revenue', 'Product Sales', '4020', 'Sales Discounts', 'Customer discounts on sales.');
        $taxPayableAccount = $service->getOrCreateAccount($business, 'liability', 'Accrued Expenses and Liabilities', '2120', 'Sales Tax Payable', 'Sales tax collected from customers.');
        $deliveryRevenueAccount = $service->getOrCreateAccount($business, 'revenue', 'Other Operating Revenue', '4220', 'Delivery Revenue', 'Revenue from delivery charges.');

        $lines = [];

        // Debit: Bank or Cash (for amount paid)
        if ((float) $this->amount_paid > 0) {
            $paymentMethod = $this->paymentMethod;
            if ($paymentMethod) {
                $settlementAccount = $paymentMethod->settlementAccount;
                if ($settlementAccount && $settlementAccount->account_id) {
                    $lines[] = [
                        'account_id' => $settlementAccount->account_id,
                        'debit' => (float) $this->amount_paid,
                        'credit' => 0.00,
                        'notes' => 'Cash/Bank received for sale '.$this->reference,
                    ];
                }
            }
        }

        // Debit: Accounts Receivable (for amount due)
        if ((float) $this->amount_due > 0) {
            $lines[] = [
                'account_id' => $arAccount->id,
                'debit' => (float) $this->amount_due,
                'credit' => 0.00,
                'notes' => 'Outstanding receivable for sale '.$this->reference,
            ];
        }

        // Debit: Sales Discounts (if any)
        if ((float) $this->discount_amount > 0) {
            $lines[] = [
                'account_id' => $discountAccount->id,
                'debit' => (float) $this->discount_amount,
                'credit' => 0.00,
                'notes' => 'Discounts applied on sale '.$this->reference,
            ];
        }

        // Credit: Sales Revenue (gross amount of items)
        $grossTotal = $this->items->sum(fn ($item) => $item->quantity * $item->unit_price);
        if ($grossTotal > 0) {
            $lines[] = [
                'account_id' => $salesRevenueAccount->id,
                'debit' => 0.00,
                'credit' => (float) $grossTotal,
                'notes' => 'Gross sales revenue for '.$this->reference,
            ];
        }

        // Credit: Tax Payable
        if ((float) $this->tax_amount > 0) {
            $lines[] = [
                'account_id' => $taxPayableAccount->id,
                'debit' => 0.00,
                'credit' => (float) $this->tax_amount,
                'notes' => 'Tax collected on sale '.$this->reference,
            ];
        }

        // Credit: Delivery Revenue
        if ((float) $this->delivery_charge > 0) {
            $lines[] = [
                'account_id' => $deliveryRevenueAccount->id,
                'debit' => 0.00,
                'credit' => (float) $this->delivery_charge,
                'notes' => 'Delivery fee revenue for sale '.$this->reference,
            ];
        }

        // Try to balance any minor rounding differences by adjusting Sales Revenue
        $totalDebits = array_sum(array_column($lines, 'debit'));
        $totalCredits = array_sum(array_column($lines, 'credit'));
        $diff = round($totalDebits - $totalCredits, 2);

        if ($diff != 0.00 && count($lines) > 0) {
            foreach ($lines as &$line) {
                if ($line['account_id'] === $salesRevenueAccount->id) {
                    $line['credit'] = round($line['credit'] + $diff, 2);
                    break;
                }
            }
        }

        if (count($lines) > 0) {
            $service->createEntry(
                $business,
                $this->sold_at ?? now(),
                $this->reference,
                'Journal entry for Sale '.$this->reference,
                $lines,
                $this
            );
        }

        // COGS and Inventory Entry
        $cogsLines = [];
        $cogsAccount = $service->getOrCreateAccount($business, 'expense', 'Cost of Goods Sold', '5010', 'Cost of Sales', 'Cost of inventory sold.');
        $inventoryAccount = $service->getOrCreateAccount($business, 'asset', 'Inventory', '1210', 'Merchandise Inventory', 'Inventory held for sale.');

        $totalCost = (float) $this->items->sum(fn ($item) => $item->quantity * ($item->product?->unit_cost ?? $item->unit_price * 0.6));

        if ($totalCost > 0) {
            $cogsLines[] = [
                'account_id' => $cogsAccount->id,
                'debit' => $totalCost,
                'credit' => 0.00,
                'notes' => 'COGS for sale '.$this->reference,
            ];

            $cogsLines[] = [
                'account_id' => $inventoryAccount->id,
                'debit' => 0.00,
                'credit' => $totalCost,
                'notes' => 'Inventory reduction for sale '.$this->reference,
            ];

            // Post COGS entry using reference with suffix -COGS
            $service->createEntry(
                $business,
                $this->sold_at ?? now(),
                $this->reference.'-COGS',
                'COGS entry for Sale '.$this->reference,
                $cogsLines
            );
        }
    }
}
