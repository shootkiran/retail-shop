<?php

namespace App\Services;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubtype;
use App\Models\Business;
use Illuminate\Support\Collection;

class ChartOfAccountsService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function config(): array
    {
        $config = config('chart-of-accounts.categories', []);

        return is_array($config) ? $config : [];
    }

    public function seedBusiness(Business $business): void
    {
        foreach ($this->config() as $categoryValue => $category) {
            foreach ($category['subtypes'] as $subtypeKey => $subtypeData) {
                $subtype = AccountSubtype::query()->withoutGlobalScopes()->updateOrCreate(
                    [
                        'business_id' => $business->getKey(),
                        'category' => $categoryValue,
                        'name' => $subtypeData['label'],
                    ],
                    [
                        'description' => $subtypeData['description'] ?? null,
                        'code_start' => (int) $subtypeData['code_start'],
                        'code_end' => (int) $subtypeData['code_end'],
                        'sort_order' => (int) ($category['sort'] ?? 0),
                        'is_active' => true,
                    ]
                );

                foreach ($subtypeData['accounts'] ?? [] as $accountData) {
                    Account::query()->withoutGlobalScopes()->updateOrCreate(
                        [
                            'business_id' => $business->getKey(),
                            'code' => (string) $accountData['code'],
                        ],
                        [
                            'account_subtype_id' => $subtype->getKey(),
                            'name' => $accountData['name'],
                            'description' => $accountData['description'] ?? null,
                            'archived' => false,
                        ]
                    );
                }
            }
        }
    }

    /**
     * @return Collection<int, array{
     *     value:string,
     *     label:string
     * }>
     */
    public function categoryTabs(): Collection
    {
        return collect($this->config())
            ->map(fn (array $category, string $value): array => [
                'value' => $value,
                'label' => $category['label'],
            ])
            ->values();
    }

    public function structure(Business $business): Collection
    {
        return AccountSubtype::withoutGlobalScopes()
            ->where('business_id', $business->getKey())
            ->with(['accounts' => fn ($query) => $query->orderBy('code')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (AccountSubtype $subtype): string => $subtype->category->value);
    }

    public function subtypeOptionsForCategory(string $category): array
    {
        return AccountSubtype::withoutGlobalScopes()
            ->where('category', $category)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function nextAccountCode(AccountSubtype $subtype): string
    {
        $existing = Account::query()->withoutGlobalScopes()
            ->where('business_id', $subtype->business_id)
            ->where('account_subtype_id', $subtype->getKey())
            ->orderByDesc('code')
            ->value('code');

        if (filled($existing) && is_numeric($existing)) {
            $candidate = (int) $existing + (int) config('chart-of-accounts.code_step', 10);
        } else {
            $candidate = $subtype->code_start + (int) config('chart-of-accounts.code_step', 10);
        }

        if ($candidate > $subtype->code_end) {
            $candidate = $subtype->code_end;
        }

        while (
            Account::query()->withoutGlobalScopes()
                ->where('business_id', $subtype->business_id)
                ->where('code', (string) $candidate)
                ->exists()
        ) {
            $candidate += (int) config('chart-of-accounts.code_step', 10);

            if ($candidate > $subtype->code_end) {
                $candidate = $subtype->code_end;
                break;
            }
        }

        return (string) $candidate;
    }

    /**
     * @param  array{name:string,description?:string|null,archived?:bool|null}  $data
     */
    public function createAccount(AccountSubtype $subtype, array $data): Account
    {
        return Account::query()->withoutGlobalScopes()->create([
            'business_id' => $subtype->business_id,
            'account_subtype_id' => $subtype->getKey(),
            'code' => $this->nextAccountCode($subtype),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'archived' => (bool) ($data['archived'] ?? false),
        ]);
    }

    /**
     * @param  array{name:string,description?:string|null,archived?:bool|null}  $data
     */
    public function updateAccount(Account $account, array $data): Account
    {
        $account->forceFill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'archived' => (bool) ($data['archived'] ?? false),
        ])->save();

        return $account;
    }
}
