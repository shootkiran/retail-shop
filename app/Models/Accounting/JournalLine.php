<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $journal_entry_id
 * @property int $account_id
 * @property float|string $debit
 * @property float|string $credit
 * @property string|null $reference
 * @property string|null $notes
 */
class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'reference',
        'notes',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (JournalLine $line): void {
            if ($line->isDirty('journal_entry_id')) {
                $originalEntryId = $line->getOriginal('journal_entry_id');
                if ($originalEntryId) {
                    $originalEntry = JournalEntry::find($originalEntryId);
                    if ($originalEntry) {
                        $originalEntry->checkPeriodLock();
                    }
                }
                $newEntryId = $line->journal_entry_id;
                if ($newEntryId) {
                    $newEntry = JournalEntry::find($newEntryId);
                    if ($newEntry) {
                        $newEntry->checkPeriodLock();
                    }
                }
            } else {
                if ($line->entry) {
                    $line->entry->checkPeriodLock();
                }
            }
        });

        static::deleting(function (JournalLine $line): void {
            $parentId = $line->getOriginal('journal_entry_id') ?? $line->journal_entry_id;
            if ($parentId) {
                $parent = JournalEntry::find($parentId);
                if ($parent) {
                    $parent->checkPeriodLock();
                }
            }
        });
    }

    /** @return BelongsTo<JournalEntry, self> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /** @return BelongsTo<Account, self> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
