<?php

namespace App\Models;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubtype;
use App\Models\Concerns\BelongsToBusiness;
use App\Services\ChartOfAccountsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int|null $business_id
 * @property int|null $pos_terminal_id
 * @property string $name
 * @property string|null $code
 * @property float|string $opening_balance
 * @property bool $is_active
 */
class CashRegister extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'pos_terminal_id',
        'name',
        'code',
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
        static::creating(function (CashRegister $cashRegister): void {
            if (blank($cashRegister->account_id)) {
                $cashRegister->associateLedgerAccount();
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
                    'name' => $this->name.' (Cash Register)',
                    'description' => 'System generated ledger account for cash register '.$this->name,
                    'archived' => false,
                ]);

            $this->account_id = $account->id;
        }
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function entries(): MorphMany
    {
        return $this->morphMany(FinancialEntry::class, 'accountable');
    }

    public function getCurrentBalanceAttribute(): float
    {
        $credits = $this->entries()->where('direction', 'credit')->sum('amount');
        $debits = $this->entries()->where('direction', 'debit')->sum('amount');

        return round((float) $this->opening_balance + $credits - $debits, 2);
    }
}
