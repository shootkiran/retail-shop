<?php

namespace App\Models\Accounting;

use App\Models\ProductItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $credit_note_id
 * @property int $product_item_id
 * @property float|string $quantity
 * @property float|string $unit_price
 * @property float|string $tax_amount
 * @property float|string $total_amount
 */
class CreditNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'product_item_id',
        'quantity',
        'unit_price',
        'tax_amount',
        'total_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (CreditNoteItem $item): void {
            if ($item->isDirty('credit_note_id')) {
                $originalNoteId = $item->getOriginal('credit_note_id');
                if ($originalNoteId) {
                    $originalNote = CreditNote::find($originalNoteId);
                    if ($originalNote) {
                        $originalNote->checkTransactionPeriodLock('refunded_at');
                    }
                }
                $newNoteId = $item->credit_note_id;
                if ($newNoteId) {
                    $newNote = CreditNote::find($newNoteId);
                    if ($newNote) {
                        $newNote->checkTransactionPeriodLock('refunded_at');
                    }
                }
            } else {
                if ($item->note) {
                    $item->note->checkTransactionPeriodLock('refunded_at');
                }
            }

            $item->tax_amount ??= 0.00;
            $item->total_amount = max(($item->quantity * $item->unit_price) + $item->tax_amount, 0.00);
        });

        static::created(function (CreditNoteItem $item): void {
            // Re-increment stock quantity
            ProductItem::query()
                ->whereKey($item->product_item_id)
                ->increment('stock_quantity', (float) $item->quantity);
        });

        static::updating(function (CreditNoteItem $item): void {
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

        static::saved(function (CreditNoteItem $item): void {
            if ($item->isDirty('credit_note_id')) {
                $originalNoteId = $item->getOriginal('credit_note_id');
                if ($originalNoteId) {
                    $originalNote = CreditNote::find($originalNoteId);
                    if ($originalNote) {
                        $originalNote->refreshTotals();
                        $originalNote->syncJournalEntry();
                    }
                }
                $newNoteId = $item->credit_note_id;
                if ($newNoteId) {
                    $newNote = CreditNote::find($newNoteId);
                    if ($newNote) {
                        $newNote->refreshTotals();
                        $newNote->syncJournalEntry();
                    }
                }
            } else {
                if ($item->note) {
                    $item->note->refreshTotals();
                    $item->note->syncJournalEntry();
                }
            }
        });

        static::deleting(function (CreditNoteItem $item): void {
            $parentId = $item->getOriginal('credit_note_id') ?? $item->credit_note_id;
            if ($parentId) {
                $parent = CreditNote::find($parentId);
                if ($parent) {
                    $parent->checkTransactionPeriodLock('refunded_at');
                }
            }
        });

        static::deleted(function (CreditNoteItem $item): void {
            // Re-decrement stock quantity on removal
            ProductItem::query()
                ->whereKey($item->product_item_id)
                ->decrement('stock_quantity', (float) $item->quantity);

            if ($item->note) {
                $item->note->refreshTotals();
                $item->note->syncJournalEntry();
            }
        });
    }

    /** @return BelongsTo<CreditNote, self> */
    public function note(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    /** @return BelongsTo<ProductItem, self> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductItem::class, 'product_item_id');
    }
}
