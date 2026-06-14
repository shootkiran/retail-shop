<?php

namespace App\Models\Accounting;

use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\Concerns\BelongsToBusiness;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $business_id
 * @property string $entry_date
 * @property string|null $reference
 * @property string|null $description
 * @property string|null $source_type
 * @property int|null $source_id
 */
class JournalEntry extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'entry_date',
        'reference',
        'description',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (JournalEntry $entry): void {
            $entry->checkPeriodLock();
        });

        static::deleting(function (JournalEntry $entry): void {
            $entry->checkPeriodLock(true);
        });
    }

    public function checkPeriodLock(bool $isDeleting = false): void
    {
        $businessId = $this->getOriginal('business_id') ?? $this->business_id;
        if (! $businessId) {
            return;
        }

        $businessIds = array_unique(array_filter([
            $this->getOriginal('business_id'),
            $this->business_id,
            $businessId
        ]));

        $originalDate = $this->getOriginal('entry_date');
        $currentDate = $this->entry_date;

        foreach ($businessIds as $bizId) {
            $settings = BusinessSetting::withoutGlobalScopes()
                ->where('business_id', $bizId)
                ->first();
            if ($settings && $settings->period_lock_date) {
                $lockDate = Carbon::parse($settings->period_lock_date)->startOfDay();

                if ($originalDate) {
                    $origDateParsed = Carbon::parse($originalDate)->startOfDay();
                    if ($origDateParsed->lessThanOrEqualTo($lockDate)) {
                        throw new \RuntimeException("This transaction falls within a locked fiscal period (Lock Date: {$lockDate->toDateString()}). Modifications are blocked.");
                    }
                }

                if ($currentDate) {
                    $currDateParsed = Carbon::parse($currentDate)->startOfDay();
                    if ($currDateParsed->lessThanOrEqualTo($lockDate)) {
                        throw new \RuntimeException("This transaction falls within a locked fiscal period (Lock Date: {$lockDate->toDateString()}). Modifications are blocked.");
                    }
                }
            }
        }
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return HasMany<JournalLine, self> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Verify that debits and credits balance.
     */
    public function balances(): bool
    {
        $debits = $this->lines()->sum('debit');
        $credits = $this->lines()->sum('credit');

        return abs((float) $debits - (float) $credits) < 0.001;
    }
}
