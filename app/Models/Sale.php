<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'payment_method_id',
        'reference',
        'status',
        'payment_status',
        'payment_type',
        'total_amount',
        'discount_amount',
        'order_discount',
        'tax_amount',
        'grand_total',
        'amount_paid',
        'amount_due',
        'notes',
        'sold_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'order_discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'sold_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sale $sale): void {
            if (blank($sale->reference)) {
                $sale->reference = 'SL-' . Str::upper(Str::ulid());
            }
        });

        static::saved(function (Sale $sale): void {
            $sale->refreshTotals();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function refreshTotals(): void
    {
        if (! $this->exists) {
            return;
        }

        $this->loadMissing('items');

        $lineTotal = $this->items->sum(fn (SaleItem $item) => $item->quantity * $item->unit_price);
        $lineDiscount = $this->items->sum('discount_amount');
        $orderDiscount = (float) $this->order_discount;
        $totalDiscount = $lineDiscount + $orderDiscount;

        $this->forceFill([
            'total_amount' => $lineTotal,
            'discount_amount' => $totalDiscount,
            'grand_total' => max($lineTotal - $totalDiscount + $this->tax_amount, 0),
        ]);

        $this->amount_due = max($this->grand_total - $this->amount_paid, 0);

        if ($this->isDirty(['total_amount', 'discount_amount', 'grand_total', 'amount_due'])) {
            $this->saveQuietly();
        }
    }
}
