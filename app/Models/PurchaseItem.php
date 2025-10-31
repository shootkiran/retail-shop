<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_item_id',
        'quantity',
        'unit_cost',
        'discount_amount',
        'total_amount',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (PurchaseItem $item): void {
            $item->discount_amount ??= 0;
            $item->total_amount = max(($item->quantity * $item->unit_cost) - $item->discount_amount, 0);
        });

        static::saved(function (PurchaseItem $item): void {
            $item->purchase?->refreshTotals();
        });

        static::deleted(function (PurchaseItem $item): void {
            if ($item->purchase && $item->purchase->exists) {
                $item->purchase->refreshTotals();
            }
        });
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductItem::class, 'product_item_id');
    }
}
