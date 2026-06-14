<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubtype;
use App\Models\Accounting\JournalEntry;
use App\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    /**
     * Create a balanced double-entry journal transaction.
     *
     * @param  array<int, array{
     *     account_id?: int,
     *     account_code?: string|int,
     *     debit: float|int,
     *     credit: float|int,
     *     reference?: string|null,
     *     notes?: string|null
     * }>  $lines
     */
    public function createEntry(
        Business $business,
        Carbon|string $date,
        ?string $reference,
        ?string $description,
        array $lines,
        ?Model $source = null,
        bool $throwOnUnbalanced = false
    ): ?JournalEntry {
        $date = Carbon::parse($date);
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $processedLines = [];

        foreach ($lines as $line) {
            $debit = round((float) ($line['debit'] ?? 0.0), 2);
            $credit = round((float) ($line['credit'] ?? 0.0), 2);

            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            $accountId = $line['account_id'] ?? null;
            if (! $accountId && isset($line['account_code'])) {
                $account = Account::withoutGlobalScopes()
                    ->where('business_id', $business->id)
                    ->where('code', (string) $line['account_code'])
                    ->first();

                if ($account) {
                    $accountId = $account->id;
                } else {
                    throw new \InvalidArgumentException("General Ledger Account with code {$line['account_code']} not found for business.");
                }
            }

            if (! $accountId) {
                throw new \InvalidArgumentException('Each journal line must specify a valid account_id or account_code.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $processedLines[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'reference' => $line['reference'] ?? null,
                'notes' => $line['notes'] ?? null,
            ];
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            if ($throwOnUnbalanced) {
                throw new \RuntimeException("Journal Entry is not balanced. Total Debits: {$totalDebit}, Total Credits: {$totalCredit}. Difference: ".($totalDebit - $totalCredit));
            }

            // Delete existing journal entry for this source if it exists but no longer balances
            if ($source) {
                JournalEntry::withoutGlobalScopes()
                    ->where('source_type', $source->getMorphClass())
                    ->where('source_id', $source->getKey())
                    ->delete();
            }

            return null;
        }

        return DB::transaction(function () use ($business, $date, $reference, $description, $processedLines, $source) {
            if ($source) {
                JournalEntry::withoutGlobalScopes()
                    ->where('source_type', $source->getMorphClass())
                    ->where('source_id', $source->getKey())
                    ->delete();
            }

            $entry = JournalEntry::create([
                'business_id' => $business->id,
                'entry_date' => $date->toDateString(),
                'reference' => $reference,
                'description' => $description,
                'source_type' => $source ? $source->getMorphClass() : null,
                'source_id' => $source ? $source->getKey() : null,
            ]);

            foreach ($processedLines as $processedLine) {
                $entry->lines()->create($processedLine);
            }

            return $entry;
        });
    }

    /**
     * Get or create a default account for a given category/subtype/code/name.
     */
    public function getOrCreateAccount(Business $business, string $category, string $subtypeName, string $code, string $name, string $description = ''): Account
    {
        $account = Account::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('code', $code)
            ->first();

        if ($account) {
            return $account;
        }

        $subtype = AccountSubtype::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('category', $category)
            ->where('name', $subtypeName)
            ->first();

        if (! $subtype) {
            // Create subtype dynamically
            $subtype = AccountSubtype::withoutGlobalScopes()->create([
                'business_id' => $business->id,
                'category' => $category,
                'name' => $subtypeName,
                'code_start' => (int) substr($code, 0, 2).'00',
                'code_end' => (int) substr($code, 0, 2).'99',
                'sort_order' => 10,
                'is_active' => true,
            ]);
        }

        return Account::withoutGlobalScopes()->create([
            'business_id' => $business->id,
            'account_subtype_id' => $subtype->id,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'archived' => false,
        ]);
    }
}
