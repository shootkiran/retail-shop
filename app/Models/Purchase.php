<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'payment_method_id',
        'reference',
        'status',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'amount_paid',
        'amount_due',
        'purchased_at',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'purchased_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase): void {
            if (blank($purchase->reference)) {
                $purchase->reference = 'PO-' . Str::upper(Str::ulid());
            }
        });

        static::saved(function (Purchase $purchase): void {
            $purchase->refreshTotals();
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function refreshTotals(): void
    {
        if (! $this->exists) {
            return;
        }

        $this->loadMissing('items');

        $lineTotal = $this->items->sum(fn (PurchaseItem $item) => $item->quantity * $item->unit_cost);
        $lineDiscount = $this->items->sum('discount_amount');

        $this->forceFill([
            'total_amount' => $lineTotal,
            'discount_amount' => $lineDiscount,
            'grand_total' => max($lineTotal - $lineDiscount + $this->tax_amount, 0),
        ]);

        $this->amount_due = max($this->grand_total - $this->amount_paid, 0);

        if ($this->isDirty(['total_amount', 'discount_amount', 'grand_total', 'amount_due'])) {
            $this->saveQuietly();
        }
    }
}
