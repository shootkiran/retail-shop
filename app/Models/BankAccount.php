<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

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
