<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'slug',
        'country',
        'timezone',
        'currency_code',
        'currency_symbol',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Business $business): void {
            if (blank($business->slug)) {
                $business->slug = Str::slug($business->name);
            }
        });

        static::created(function (Business $business): void {
            $business->settings()->firstOrCreate([], [
                'country' => $business->country ?: config('retail.country'),
                'timezone' => $business->timezone ?: config('retail.timezone'),
                'currency_code' => $business->currency_code ?: config('retail.currency.code'),
                'currency_symbol' => $business->currency_symbol ?: config('retail.currency.symbol'),
                'currency_decimal_places' => config('retail.currency.decimal_places'),
            ]);

            $business->terminals()->firstOrCreate([
                'code' => 'MAIN',
            ], [
                'name' => 'Main Counter',
                'location' => 'Front counter',
                'is_active' => true,
            ]);
        });
    }

    public function settings(): HasOne
    {
        return $this->hasOne(BusinessSetting::class);
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(PosTerminal::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'office_type', 'is_active'])
            ->withTimestamps();
    }
}
