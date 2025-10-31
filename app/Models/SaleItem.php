<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_item_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'total_amount',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (SaleItem $item): void {
            $item->discount_amount ??= 0;
            $item->total_amount = max(($item->quantity * $item->unit_price) - $item->discount_amount, 0);
        });

        static::saved(function (SaleItem $item): void {
            $item->sale?->refreshTotals();
        });

        static::deleted(function (SaleItem $item): void {
            if ($item->sale && $item->sale->exists) {
                $item->sale->refreshTotals();
            }
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductItem::class, 'product_item_id');
    }
}
