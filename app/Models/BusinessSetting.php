<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSetting extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'country',
        'timezone',
        'currency_code',
        'currency_symbol',
        'currency_decimal_places',
        'date_format',
        'time_format',
        'invoice_prefix',
        'invoice_footer',
        'period_lock_date',
    ];

    protected $casts = [
        'currency_decimal_places' => 'integer',
        'invoice_footer' => 'array',
        'period_lock_date' => 'date',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
