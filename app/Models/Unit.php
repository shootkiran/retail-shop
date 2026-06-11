<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unit extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'symbol',
        'multiplier_to_base',
        'is_base',
        'is_active',
    ];

    protected $casts = [
        'multiplier_to_base' => 'decimal:4',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
