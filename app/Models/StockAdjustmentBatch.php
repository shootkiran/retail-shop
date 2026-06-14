<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $business_id
 * @property string $reference
 * @property int $counted_by_user_id
 * @property int|null $posted_by_user_id
 * @property Carbon|null $counted_at
 * @property Carbon|null $posted_at
 * @property string $posting_mode
 * @property string $status
 * @property float|string $variance_value
 * @property string|null $notes
 * @property string|null $variance_reason
 * @property-read User|null $countedBy
 * @property-read User|null $postedBy
 */
class StockAdjustmentBatch extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'reference',
        'counted_by_user_id',
        'posted_by_user_id',
        'counted_at',
        'posted_at',
        'posting_mode',
        'status',
        'variance_value',
        'notes',
        'variance_reason',
    ];

    protected $casts = [
        'counted_at' => 'datetime',
        'posted_at' => 'datetime',
        'variance_value' => 'decimal:2',
    ];

    /** @return BelongsTo<User, self> */
    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by_user_id');
    }

    /** @return BelongsTo<User, self> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    /** @return HasMany<StockAdjustmentLine, self> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class, 'stock_adjustment_batch_id');
    }
}
