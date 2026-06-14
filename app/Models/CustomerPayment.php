<?php

namespace App\Models;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToBusiness;
use App\Services\Accounting\JournalEntryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerPayment extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'customer_id',
        'sale_id',
        'bank_account_id',
        'cash_register_id',
        'amount',
        'payment_date',
        'method',
        'reference',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (CustomerPayment $payment): void {
            $payment->checkTransactionPeriodLock('payment_date');
        });

        static::deleting(function (CustomerPayment $payment): void {
            $payment->checkTransactionPeriodLock('payment_date', true);
        });

        static::creating(function (CustomerPayment $payment): void {
            if (blank($payment->reference)) {
                $payment->reference = 'CP-'.Str::upper(Str::ulid());
            }
        });

        static::created(function (CustomerPayment $payment): void {
            $payment->customer?->decrement('outstanding_balance', (float) $payment->amount);

            $account = $payment->resolveAccount();
            if ($account) {
                FinancialEntry::create([
                    'accountable_type' => $account::class,
                    'accountable_id' => $account->getKey(),
                    'entry_type' => 'customer_payment',
                    'direction' => 'credit',
                    'amount' => $payment->amount,
                    'entry_date' => $payment->payment_date,
                    'reference' => $payment->reference,
                    'notes' => $payment->notes,
                ]);
            }

            $payment->syncJournalEntry();
        });

        static::deleted(function (CustomerPayment $payment): void {
            $payment->customer?->increment('outstanding_balance', (float) $payment->amount);

            FinancialEntry::query()
                ->where('entry_type', 'customer_payment')
                ->where('reference', $payment->reference)
                ->delete();

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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    protected function resolveAccount(): BankAccount|CashRegister|null
    {
        return $this->bankAccount ?? $this->cashRegister;
    }

    public function syncJournalEntry(): void
    {
        $business = $this->business;
        if (! $business) {
            return;
        }

        $service = app(JournalEntryService::class);
        $arAccount = $service->getOrCreateAccount($business, 'asset', 'Receivables', '1110', 'Accounts Receivable', 'Customer unpaid invoice balances.');

        $account = $this->resolveAccount();
        if (! $account || ! $account->account_id) {
            return;
        }

        $lines = [
            [
                'account_id' => $account->account_id,
                'debit' => (float) $this->amount,
                'credit' => 0.00,
                'notes' => 'Customer payment received '.$this->reference,
            ],
            [
                'account_id' => $arAccount->id,
                'debit' => 0.00,
                'credit' => (float) $this->amount,
                'notes' => 'Receivable reduction from customer payment '.$this->reference,
            ],
        ];

        $service->createEntry(
            $business,
            $this->payment_date ?? now(),
            $this->reference,
            'Journal entry for Customer Payment '.$this->reference,
            $lines,
            $this
        );
    }
}
