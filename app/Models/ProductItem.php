<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $business_id
 * @property int|null $product_category_id
 * @property int|null $vendor_id
 * @property int|null $base_unit_id
 * @property string $name
 * @property string|null $sku
 * @property string|null $barcode
 * @property string|null $description
 * @property float|string $unit_cost
 * @property float|string $unit_price
 * @property float|string $tax_rate
 * @property int|float $stock_quantity
 * @property int|float $reorder_level
 * @property bool $is_active
 * @property-read ProductCategory|null $category
 * @property-read Vendor|null $vendor
 * @property-read Unit|null $baseUnit
 */
class ProductItem extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'product_category_id',
        'vendor_id',
        'base_unit_id',
        'name',
        'sku',
        'barcode',
        'description',
        'unit_cost',
        'unit_price',
        'tax_rate',
        'stock_quantity',
        'reorder_level',
        'is_active',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<ProductCategory, self> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** @return BelongsTo<Vendor, self> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<Unit, self> */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    /** @return HasMany<SaleItem, self> */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** @return HasMany<PurchaseItem, self> */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function getStockDisplayAttribute(): string
    {
        $unit = $this->baseUnit;

        if (! $unit) {
            return number_format((float) $this->stock_quantity, 0).' pcs';
        }

        return number_format($unit->fromBase((float) $this->stock_quantity), 2).' '.($unit->symbol ?: $unit->name);
    }
}
