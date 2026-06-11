<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
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
        static::creating(function (CustomerPayment $payment): void {
            if (blank($payment->reference)) {
                $payment->reference = 'CP-' . Str::upper(Str::ulid());
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
        });

        static::deleted(function (CustomerPayment $payment): void {
            $payment->customer?->increment('outstanding_balance', (float) $payment->amount);

            FinancialEntry::query()
                ->where('entry_type', 'customer_payment')
                ->where('reference', $payment->reference)
                ->delete();
        });
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
}
