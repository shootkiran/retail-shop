<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\AccountCategory;
use App\Models\Business;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $business_id
 * @property AccountCategory $category
 * @property string $name
 * @property string|null $description
 * @property int $code_start
 * @property int $code_end
 * @property int $sort_order
 * @property bool $is_active
 */
class AccountSubtype extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'category',
        'name',
        'description',
        'code_start',
        'code_end',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'category' => AccountCategory::class,
        'code_start' => 'integer',
        'code_end' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return HasMany<Account, self> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'account_subtype_id');
    }
}
