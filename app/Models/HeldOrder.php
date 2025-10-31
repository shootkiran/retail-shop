<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'payment_method_id',
        'label',
        'payment_type',
        'order_discount',
        'tax_rate',
        'amount_paid',
        'cart',
    ];

    protected $casts = [
        'cart' => 'array',
        'order_discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
