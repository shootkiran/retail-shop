<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $business_id
 * @property string $accountable_type
 * @property int $accountable_id
 * @property string $entry_type
 * @property string $direction
 * @property float|string $amount
 * @property string|null $reference
 * @property string|null $notes
 */
class FinancialEntry extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'accountable_type',
        'accountable_id',
        'entry_type',
        'direction',
        'amount',
        'entry_date',
        'reference',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'entry_date' => 'date',
    ];

    public function accountable(): MorphTo
    {
        return $this->morphTo();
    }
}
