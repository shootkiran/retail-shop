<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'purchase_id',
        'product_item_id',
        'unit_id',
        'quantity',
        'quantity_base',
        'unit_cost',
        'discount_amount',
        'total_amount',
    ];

    protected $casts = [
        'quantity_base' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (PurchaseItem $item): void {
            if ($item->isDirty('purchase_id')) {
                $originalPurchaseId = $item->getOriginal('purchase_id');
                if ($originalPurchaseId) {
                    $originalPurchase = Purchase::find($originalPurchaseId);
                    if ($originalPurchase) {
                        $originalPurchase->checkTransactionPeriodLock('purchased_at');
                    }
                }
                $newPurchaseId = $item->purchase_id;
                if ($newPurchaseId) {
                    $newPurchase = Purchase::find($newPurchaseId);
                    if ($newPurchase) {
                        $newPurchase->checkTransactionPeriodLock('purchased_at');
                    }
                }
            } else {
                if ($item->purchase) {
                    $item->purchase->checkTransactionPeriodLock('purchased_at');
                }
            }

            $unit = $item->unit;
            $item->quantity_base = $unit ? $unit->toBase($item->quantity) : (float) $item->quantity;
            $item->discount_amount ??= 0;
            $item->total_amount = max(($item->quantity * $item->unit_cost) - $item->discount_amount, 0);
        });

        static::created(function (PurchaseItem $item): void {
            ProductItem::query()
                ->whereKey($item->product_item_id)
                ->increment('stock_quantity', $item->quantity_base);
        });

        static::updating(function (PurchaseItem $item): void {
            $originalQuantityBase = (float) $item->getOriginal('quantity_base');
            $originalProductId = (int) $item->getOriginal('product_item_id');
            $currentProductId = (int) $item->product_item_id;

            if ($originalProductId !== $currentProductId) {
                ProductItem::query()
                    ->whereKey($originalProductId)
                    ->decrement('stock_quantity', $originalQuantityBase);

                ProductItem::query()
                    ->whereKey($currentProductId)
                    ->increment('stock_quantity', $item->quantity_base);

                return;
            }

            $delta = (float) $item->quantity_base - $originalQuantityBase;

            if ($delta !== 0.0) {
                ProductItem::query()
                    ->whereKey($currentProductId)
                    ->increment('stock_quantity', $delta);
            }
        });

        static::saved(function (PurchaseItem $item): void {
            if ($item->isDirty('purchase_id')) {
                $originalPurchaseId = $item->getOriginal('purchase_id');
                if ($originalPurchaseId) {
                    $originalPurchase = Purchase::find($originalPurchaseId);
                    if ($originalPurchase) {
                        $originalPurchase->refreshTotals();
                    }
                }
                $newPurchaseId = $item->purchase_id;
                if ($newPurchaseId) {
                    $newPurchase = Purchase::find($newPurchaseId);
                    if ($newPurchase) {
                        $newPurchase->refreshTotals();
                    }
                }
            } else {
                $item->purchase?->refreshTotals();
            }
        });

        static::deleting(function (PurchaseItem $item): void {
            $parentId = $item->getOriginal('purchase_id') ?? $item->purchase_id;
            if ($parentId) {
                $parent = Purchase::find($parentId);
                if ($parent) {
                    $parent->checkTransactionPeriodLock('purchased_at');
                }
            }
        });

        static::deleted(function (PurchaseItem $item): void {
            ProductItem::query()
                ->whereKey($item->product_item_id)
                ->decrement('stock_quantity', $item->quantity_base);
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
