<?php

namespace App\Models\Accounting;

use App\Models\Business;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $business_id
 * @property int $account_subtype_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $archived
 */
class Account extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'account_subtype_id',
        'code',
        'name',
        'description',
        'archived',
    ];

    protected $casts = [
        'code' => 'string',
        'archived' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<AccountSubtype, self> */
    public function subtype(): BelongsTo
    {
        return $this->belongsTo(AccountSubtype::class, 'account_subtype_id');
    }
}
