<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $business_id
 * @property int $stock_adjustment_batch_id
 * @property int $product_item_id
 * @property int|null $unit_id
 * @property string $unit_name_snapshot
 * @property string|null $unit_symbol_snapshot
 * @property float|string $unit_multiplier_snapshot
 * @property float|string $system_quantity_base
 * @property float|string $system_quantity_display
 * @property float|string|null $counted_quantity
 * @property float|string|null $counted_quantity_base
 * @property float|string $variance_base
 * @property float|string $variance_value
 * @property float|string $unit_cost
 * @property string|null $notes
 */
class StockAdjustmentLine extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'stock_adjustment_batch_id',
        'product_item_id',
        'unit_id',
        'unit_name_snapshot',
        'unit_symbol_snapshot',
        'unit_multiplier_snapshot',
        'system_quantity_base',
        'system_quantity_display',
        'counted_quantity',
        'counted_quantity_base',
        'variance_base',
        'variance_value',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'unit_multiplier_snapshot' => 'decimal:4',
        'system_quantity_base' => 'decimal:4',
        'system_quantity_display' => 'decimal:4',
        'counted_quantity' => 'decimal:4',
        'counted_quantity_base' => 'decimal:4',
        'variance_base' => 'decimal:4',
        'variance_value' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    /** @return BelongsTo<StockAdjustmentBatch, self> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockAdjustmentBatch::class, 'stock_adjustment_batch_id');
    }

    /** @return BelongsTo<ProductItem, self> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductItem::class, 'product_item_id');
    }

    /** @return BelongsTo<Unit, self> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
