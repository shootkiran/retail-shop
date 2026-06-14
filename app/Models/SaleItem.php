<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'sale_id',
        'product_item_id',
        'unit_id',
        'quantity',
        'quantity_base',
        'unit_price',
        'discount_amount',
        'total_amount',
    ];

    protected $casts = [
        'quantity_base' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (SaleItem $item): void {
            if ($item->isDirty('sale_id')) {
                $originalSaleId = $item->getOriginal('sale_id');
                if ($originalSaleId) {
                    $originalSale = Sale::find($originalSaleId);
                    if ($originalSale) {
                        $originalSale->checkTransactionPeriodLock('sold_at');
                    }
                }
                $newSaleId = $item->sale_id;
                if ($newSaleId) {
                    $newSale = Sale::find($newSaleId);
                    if ($newSale) {
                        $newSale->checkTransactionPeriodLock('sold_at');
                    }
                }
            } else {
                if ($item->sale) {
                    $item->sale->checkTransactionPeriodLock('sold_at');
                }
            }

            $unit = $item->unit;
            $item->quantity_base = $unit ? $unit->toBase($item->quantity) : (float) $item->quantity;
            $item->discount_amount ??= 0;
            $item->total_amount = max(($item->quantity * $item->unit_price) - $item->discount_amount, 0);
        });

        static::created(function (SaleItem $item): void {
            ProductItem::query()
                ->whereKey($item->product_item_id)
                ->decrement('stock_quantity', $item->quantity_base);
        });

        static::updating(function (SaleItem $item): void {
            $originalQuantityBase = (float) $item->getOriginal('quantity_base');
            $originalProductId = (int) $item->getOriginal('product_item_id');
            $currentProductId = (int) $item->product_item_id;

            if ($originalProductId !== $currentProductId) {
                ProductItem::query()
                    ->whereKey($originalProductId)
                    ->increment('stock_quantity', $originalQuantityBase);

                ProductItem::query()
                    ->whereKey($currentProductId)
                    ->decrement('stock_quantity', $item->quantity_base);

                return;
            }

            $delta = (float) $item->quantity_base - $originalQuantityBase;

            if ($delta !== 0.0) {
                ProductItem::query()
                    ->whereKey($currentProductId)
                    ->decrement('stock_quantity', $delta);
            }
        });

        static::saved(function (SaleItem $item): void {
            if ($item->isDirty('sale_id')) {
                $originalSaleId = $item->getOriginal('sale_id');
                if ($originalSaleId) {
                    $originalSale = Sale::find($originalSaleId);
                    if ($originalSale) {
                        $originalSale->refreshTotals();
                    }
                }
                $newSaleId = $item->sale_id;
                if ($newSaleId) {
                    $newSale = Sale::find($newSaleId);
                    if ($newSale) {
                        $newSale->refreshTotals();
                    }
                }
            } else {
                $item->sale?->refreshTotals();
            }
        });

        static::deleting(function (SaleItem $item): void {
            $parentId = $item->getOriginal('sale_id') ?? $item->sale_id;
            if ($parentId) {
                $parent = Sale::find($parentId);
                if ($parent) {
                    $parent->checkTransactionPeriodLock('sold_at');
                }
            }
        });

        static::deleted(function (SaleItem $item): void {
            ProductItem::query()
                ->whereKey($item->product_item_id)
                ->increment('stock_quantity', $item->quantity_base);
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
