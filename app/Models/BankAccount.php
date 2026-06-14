<?php

namespace App\Models;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubtype;
use App\Models\Concerns\BelongsToBusiness;
use App\Services\ChartOfAccountsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int|null $business_id
 * @property string $name
 * @property string|null $bank_name
 * @property string|null $account_number
 * @property string|null $account_type
 * @property float|string $opening_balance
 * @property bool $is_active
 */
class BankAccount extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'bank_name',
        'account_number',
        'account_type',
        'opening_balance',
        'is_active',
        'account_id',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (BankAccount $bankAccount): void {
            if (blank($bankAccount->account_id)) {
                $bankAccount->associateLedgerAccount();
            }
        });
    }

    public function associateLedgerAccount(): void
    {
        $subtype = AccountSubtype::withoutGlobalScopes()
            ->where('business_id', $this->business_id)
            ->where('category', 'asset')
            ->where('name', 'Cash and Cash Equivalents')
            ->first();

        if ($subtype) {
            $account = Account::withoutGlobalScopes()
                ->create([
                    'business_id' => $this->business_id,
                    'account_subtype_id' => $subtype->id,
                    'code' => app(ChartOfAccountsService::class)->nextAccountCode($subtype),
                    'name' => $this->name.' ('.($this->bank_name ?? 'Bank').')',
                    'description' => 'System generated ledger account for bank account '.$this->name,
                    'archived' => false,
                ]);

            $this->account_id = $account->id;
        }
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function entries(): MorphMany
    {
        return $this->morphMany(FinancialEntry::class, 'accountable');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function getCurrentBalanceAttribute(): float
    {
        $credits = $this->entries()->where('direction', 'credit')->sum('amount');
        $debits = $this->entries()->where('direction', 'debit')->sum('amount');

        return round((float) $this->opening_balance + $credits - $debits, 2);
    }
}
