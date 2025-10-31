<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    protected $casts = [
        'name' => 'string',
    ];

    public function productItems(): HasMany
    {
        return $this->hasMany(ProductItem::class);
    }

    protected static function booted(): void
    {
        static::creating(function (ProductCategory $category): void {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function (ProductCategory $category): void {
            if ($category->isDirty('name') && blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }
}
