<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $business_id
 * @property string $name
 * @property string|null $symbol
 * @property float|string $multiplier_to_base
 * @property bool $is_base
 * @property bool $is_active
 */
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

    /** @return BelongsTo<Business, self> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function toBase(float|int|string|null $quantity): float
    {
        $multiplier = max((float) $this->multiplier_to_base, 1.0);

        return round((float) $quantity * $multiplier, 4);
    }

    public function fromBase(float|int|string|null $quantity): float
    {
        $multiplier = max((float) $this->multiplier_to_base, 1.0);

        return round((float) $quantity / $multiplier, 4);
    }
}
