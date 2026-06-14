<?php

namespace App\Models\Accounting;

use App\Models\BusinessSetting;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Customer;
use App\Models\Sale;
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
 * @property int $customer_id
 * @property int|null $sale_id
 * @property string $reference
 * @property float|string $total_amount
 * @property float|string $discount_amount
 * @property float|string $tax_amount
 * @property float|string $grand_total
 * @property string|null $notes
 * @property string $refunded_at
 */
class CreditNote extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'customer_id',
        'sale_id',
        'reference',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'notes',
        'refunded_at',
    ];

    protected $casts = [
        'refunded_at' => 'date',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (CreditNote $note): void {
            $note->checkTransactionPeriodLock('refunded_at');
        });

        static::deleting(function (CreditNote $note): void {
            $note->checkTransactionPeriodLock('refunded_at', true);
        });

        static::creating(function (CreditNote $note): void {
            if (blank($note->reference)) {
                $note->reference = 'CN-'.Str::upper(Str::ulid());
            }
        });

        static::created(function (CreditNote $note): void {
            $note->customer?->decrement('outstanding_balance', (float) $note->grand_total);
            $note->syncJournalEntry();
        });

        static::deleted(function (CreditNote $note): void {
            $note->customer?->increment('outstanding_balance', (float) $note->grand_total);

            JournalEntry::withoutGlobalScopes()
                ->where('source_type', $note->getMorphClass())
                ->where('source_id', $note->getKey())
                ->delete();

            JournalEntry::withoutGlobalScopes()
                ->where('reference', $note->reference.'-COGS')
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return HasMany<CreditNoteItem, self> */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function refreshTotals(): void
    {
        if (! $this->exists) {
            return;
        }

        $this->loadMissing('items');

        $lineTotal = $this->items->sum(fn (CreditNoteItem $item) => $item->quantity * $item->unit_price);
        $taxTotal = $this->items->sum('tax_amount');
        $grandTotal = max($lineTotal - (float) $this->discount_amount + $taxTotal, 0.00);

        $this->forceFill([
            'total_amount' => $lineTotal,
            'tax_amount' => $taxTotal,
            'grand_total' => $grandTotal,
        ]);

        if ($this->isDirty(['total_amount', 'tax_amount', 'grand_total'])) {
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
        $arAccount = $service->getOrCreateAccount($business, 'asset', 'Receivables', '1110', 'Accounts Receivable', 'Customer unpaid invoice balances.');
        $salesReturnAccount = $service->getOrCreateAccount($business, 'revenue', 'Product Sales', '4020', 'Sales Discounts', 'Customer discounts/returns on sales.');
        $taxPayableAccount = $service->getOrCreateAccount($business, 'liability', 'Accrued Expenses and Liabilities', '2120', 'Sales Tax Payable', 'Sales tax collected from customers.');

        $lines = [];

        // Debit: Sales Returns/Discounts
        $grossRevenue = $this->items->isEmpty() ? (float) $this->total_amount : $this->items->sum(fn ($item) => $item->quantity * $item->unit_price);
        if ($grossRevenue > 0) {
            $lines[] = [
                'account_id' => $salesReturnAccount->id,
                'debit' => (float) $grossRevenue,
                'credit' => 0.00,
                'notes' => 'Sales returns/refunds for Credit Note '.$this->reference,
            ];
        }

        // Debit: Sales Tax Payable
        if ((float) $this->tax_amount > 0) {
            $lines[] = [
                'account_id' => $taxPayableAccount->id,
                'debit' => (float) $this->tax_amount,
                'credit' => 0.00,
                'notes' => 'Sales tax reversal for Credit Note '.$this->reference,
            ];
        }

        // Credit: Accounts Receivable
        if ((float) $this->grand_total > 0) {
            $lines[] = [
                'account_id' => $arAccount->id,
                'debit' => 0.00,
                'credit' => (float) $this->grand_total,
                'notes' => 'Receivable reduction for Credit Note '.$this->reference,
            ];
        }

        // Try to balance minor rounding
        $totalDebits = array_sum(array_column($lines, 'debit'));
        $totalCredits = array_sum(array_column($lines, 'credit'));
        $diff = round($totalCredits - $totalDebits, 2);

        if ($diff != 0.00 && count($lines) > 0) {
            foreach ($lines as &$line) {
                if ($line['account_id'] === $salesReturnAccount->id) {
                    $line['debit'] = round($line['debit'] + $diff, 2);
                    break;
                }
            }
        }

        if (count($lines) > 0) {
            $service->createEntry(
                $business,
                $this->refunded_at ?? now(),
                $this->reference,
                'Credit Note booking '.$this->reference,
                $lines,
                $this
            );
        }

        // Inventory Return (COGS adjustment)
        $cogsLines = [];
        $cogsAccount = $service->getOrCreateAccount($business, 'expense', 'Cost of Goods Sold', '5010', 'Cost of Sales', 'Cost of inventory sold.');
        $inventoryAccount = $service->getOrCreateAccount($business, 'asset', 'Inventory', '1210', 'Merchandise Inventory', 'Inventory held for sale.');

        $totalCost = (float) $this->items->sum(fn ($item) => $item->quantity * ($item->product?->unit_cost ?? $item->unit_price * 0.6));

        if ($totalCost > 0) {
            $cogsLines[] = [
                'account_id' => $inventoryAccount->id,
                'debit' => $totalCost,
                'credit' => 0.00,
                'notes' => 'Inventory restocked from Credit Note '.$this->reference,
            ];

            $cogsLines[] = [
                'account_id' => $cogsAccount->id,
                'debit' => 0.00,
                'credit' => $totalCost,
                'notes' => 'COGS reduction from Credit Note '.$this->reference,
            ];

            $service->createEntry(
                $business,
                $this->refunded_at ?? now(),
                $this->reference.'-COGS',
                'COGS reversal for Credit Note '.$this->reference,
                $cogsLines
            );
        }
    }
}
