<?php

namespace App\Models\Accounting;

use App\Models\BankAccount;
use App\Models\BusinessSetting;
use App\Models\CashRegister;
use App\Models\Concerns\BelongsToBusiness;
use App\Services\Accounting\JournalEntryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $business_id
 * @property int $vendor_bill_id
 * @property int|null $bank_account_id
 * @property int|null $cash_register_id
 * @property float|string $amount
 * @property string $payment_date
 * @property string $reference
 * @property string|null $notes
 */
class VendorBillPayment extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'vendor_bill_id',
        'bank_account_id',
        'cash_register_id',
        'amount',
        'payment_date',
        'reference',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (VendorBillPayment $payment): void {
            $payment->checkTransactionPeriodLock('payment_date');
        });

        static::deleting(function (VendorBillPayment $payment): void {
            $payment->checkTransactionPeriodLock('payment_date', true);
        });

        static::creating(function (VendorBillPayment $payment): void {
            if (blank($payment->reference)) {
                $payment->reference = 'VBP-'.Str::upper(Str::ulid());
            }
        });

        static::created(function (VendorBillPayment $payment): void {
            $payment->bill?->refreshTotals();
            $payment->syncJournalEntry();
        });

        static::updated(function (VendorBillPayment $payment): void {
            $payment->bill?->refreshTotals();
            $payment->syncJournalEntry();
        });

        static::deleted(function (VendorBillPayment $payment): void {
            $payment->bill?->refreshTotals();

            JournalEntry::withoutGlobalScopes()
                ->where('source_type', $payment->getMorphClass())
                ->where('source_id', $payment->getKey())
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

    /** @return BelongsTo<VendorBill, self> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }

    /** @return BelongsTo<BankAccount, self> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** @return BelongsTo<CashRegister, self> */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function resolveAccount(): BankAccount|CashRegister|null
    {
        return $this->bankAccount ?? $this->cashRegister;
    }

    public function syncJournalEntry(): void
    {
        $business = $this->business;
        if (! $business || ! $this->exists) {
            return;
        }

        $service = app(JournalEntryService::class);
        $apAccount = $service->getOrCreateAccount($business, 'liability', 'Accounts Payable', '2010', 'Accounts Payable', 'Vendor outstanding balances.');

        $account = $this->resolveAccount();
        if (! $account || ! $account->account_id) {
            return;
        }

        $lines = [
            [
                'account_id' => $apAccount->id,
                'debit' => (float) $this->amount,
                'credit' => 0.00,
                'notes' => 'Debit Accounts Payable for bill payment '.$this->reference,
            ],
            [
                'account_id' => $account->account_id,
                'debit' => 0.00,
                'credit' => (float) $this->amount,
                'notes' => 'Credit Cash/Bank from bill payment '.$this->reference,
            ],
        ];

        $service->createEntry(
            $business,
            $this->payment_date ?? now(),
            $this->reference,
            'Vendor Bill Payment '.$this->reference,
            $lines,
            $this
        );
    }
}
