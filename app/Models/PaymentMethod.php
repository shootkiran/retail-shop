<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentMethod extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'type',
        'description',
        'settlement_account_type',
        'settlement_account_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function settlementAccount(): MorphTo
    {
        return $this->morphTo();
    }
}
