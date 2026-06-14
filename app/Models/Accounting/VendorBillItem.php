<?php

namespace App\Models\Accounting;

use App\Models\ProductItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vendor_bill_id
 * @property int $product_item_id
 * @property float|string $quantity
 * @property float|string $unit_cost
 * @property float|string $tax_amount
 * @property float|string $total_amount
 */
class VendorBillItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_bill_id',
        'product_item_id',
        'quantity',
        'unit_cost',
        'tax_amount',
        'total_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (VendorBillItem $item): void {
            if ($item->isDirty('vendor_bill_id')) {
                $originalBillId = $item->getOriginal('vendor_bill_id');
                if ($originalBillId) {
                    $originalBill = VendorBill::find($originalBillId);
                    if ($originalBill) {
                        $originalBill->checkTransactionPeriodLock('bill_date');
                    }
                }
                $newBillId = $item->vendor_bill_id;
                if ($newBillId) {
                    $newBill = VendorBill::find($newBillId);
                    if ($newBill) {
                        $newBill->checkTransactionPeriodLock('bill_date');
                    }
                }
            } else {
                if ($item->bill) {
                    $item->bill->checkTransactionPeriodLock('bill_date');
                }
            }

            $item->tax_amount ??= 0.00;
            $item->total_amount = max(($item->quantity * $item->unit_cost) + $item->tax_amount, 0.00);
        });

        static::created(function (VendorBillItem $item): void {
            // Increment inventory stock
            ProductItem::query()
                ->whereKey($item->product_item_id)
                ->increment('stock_quantity', (float) $item->quantity);
        });

        static::updating(function (VendorBillItem $item): void {
            $originalQuantity = (float) $item->getOriginal('quantity');
            $originalProductId = (int) $item->getOriginal('product_item_id');
            $currentProductId = (int) $item->product_item_id;

            if ($originalProductId !== $currentProductId) {
                ProductItem::query()
                    ->whereKey($originalProductId)
                    ->decrement('stock_quantity', $originalQuantity);

                ProductItem::query()
                    ->whereKey($currentProductId)
                    ->increment('stock_quantity', (float) $item->quantity);

                return;
            }

            $delta = (float) $item->quantity - $originalQuantity;

            if ($delta !== 0.0) {
                ProductItem::query()
                    ->whereKey($currentProductId)
                    ->increment('stock_quantity', $delta);
            }
        });

        static::saved(function (VendorBillItem $item): void {
            if ($item->isDirty('vendor_bill_id')) {
                $originalBillId = $item->getOriginal('vendor_bill_id');
                if ($originalBillId) {
                    $originalBill = VendorBill::find($originalBillId);
                    if ($originalBill) {
                        $originalBill->refreshTotals();
                        if ($originalBill->status === 'posted' || $originalBill->status === 'paid' || $originalBill->status === 'partially_paid') {
                            $originalBill->syncJournalEntry();
                        } else {
                            JournalEntry::withoutGlobalScopes()
                                ->where('source_type', $originalBill->getMorphClass())
                                ->where('source_id', $originalBill->getKey())
                                ->delete();
                        }
                    }
                }
                $newBillId = $item->vendor_bill_id;
                if ($newBillId) {
                    $newBill = VendorBill::find($newBillId);
                    if ($newBill) {
                        $newBill->refreshTotals();
                        if ($newBill->status === 'posted' || $newBill->status === 'paid' || $newBill->status === 'partially_paid') {
                            $newBill->syncJournalEntry();
                        } else {
                            JournalEntry::withoutGlobalScopes()
                                ->where('source_type', $newBill->getMorphClass())
                                ->where('source_id', $newBill->getKey())
                                ->delete();
                        }
                    }
                }
            } else {
                if ($item->bill) {
                    $item->bill->refreshTotals();
                    if ($item->bill->status === 'posted' || $item->bill->status === 'paid' || $item->bill->status === 'partially_paid') {
                        $item->bill->syncJournalEntry();
                    } else {
                        JournalEntry::withoutGlobalScopes()
                            ->where('source_type', $item->bill->getMorphClass())
                            ->where('source_id', $item->bill->getKey())
                            ->delete();
                    }
                }
            }
        });

        static::deleting(function (VendorBillItem $item): void {
            $parentId = $item->getOriginal('vendor_bill_id') ?? $item->vendor_bill_id;
            if ($parentId) {
                $parent = VendorBill::find($parentId);
                if ($parent) {
                    $parent->checkTransactionPeriodLock('bill_date');
                }
            }
        });

        static::deleted(function (VendorBillItem $item): void {
            // Decrement inventory stock on removal
            ProductItem::query()
                ->whereKey($item->product_item_id)
                ->decrement('stock_quantity', (float) $item->quantity);

            if ($item->bill) {
                $bill = $item->bill;
                $bill->refreshTotals();
                if ($bill->status === 'posted' || $bill->status === 'paid' || $bill->status === 'partially_paid') {
                    $bill->syncJournalEntry();
                } else {
                    JournalEntry::withoutGlobalScopes()
                        ->where('source_type', $bill->getMorphClass())
                        ->where('source_id', $bill->getKey())
                        ->delete();
                }
            }
        });
    }

    /** @return BelongsTo<VendorBill, self> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }

    /** @return BelongsTo<ProductItem, self> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductItem::class, 'product_item_id');
    }
}
