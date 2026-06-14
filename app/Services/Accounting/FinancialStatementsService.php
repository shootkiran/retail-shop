<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalLine;
use App\Models\Business;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialStatementsService
{
    /**
     * Compute Trial Balance
     *
     * @return Collection<int, array{
     *     account_id: int,
     *     code: string,
     *     name: string,
     *     category: string,
     *     debit: float,
     *     credit: float,
     *     balance: float,
     *     balance_type: 'debit'|'credit'
     * }>
     */
    public function getTrialBalance(Business $business, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = JournalLine::query()
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.business_id', $business->id);

        if ($startDate) {
            $query->whereDate('journal_entries.entry_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('journal_entries.entry_date', '<=', $endDate);
        }

        $balances = $query->select(
            'accounts.id as account_id',
            'accounts.code',
            'accounts.name',
            'accounts.account_subtype_id',
            DB::raw('SUM(journal_lines.debit) as total_debit'),
            DB::raw('SUM(journal_lines.credit) as total_credit')
        )
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.account_subtype_id')
            ->orderBy('accounts.code')
            ->get();

        // Load subtypes to get categories
        $allAccounts = Account::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->with('subtype')
            ->get()
            ->keyBy('id');

        return $balances->map(function ($row) use ($allAccounts) {
            $account = $allAccounts->get($row->account_id);
            $category = $account?->subtype?->category?->value ?? 'asset';

            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            // Determine normal balance type
            $balanceType = in_array($category, ['asset', 'expense']) ? 'debit' : 'credit';
            $balance = $balanceType === 'debit' ? ($debit - $credit) : ($credit - $debit);

            return [
                'account_id' => (int) $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'category' => $category,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($balance, 2),
                'balance_type' => $balanceType,
            ];
        });
    }

    /**
     * Compute Profit and Loss (Income Statement)
     */
    public function getProfitAndLoss(Business $business, ?string $startDate = null, ?string $endDate = null): array
    {
        $trialBalance = $this->getTrialBalance($business, $startDate, $endDate);

        $revenueItems = $trialBalance->where('category', 'revenue');
        $expenseItems = $trialBalance->where('category', 'expense');

        // Segment expense items: cogs vs operating
        // Load accounts to check subtype category/name
        $allAccounts = Account::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->with('subtype')
            ->get()
            ->keyBy('id');

        $cogs = 0.0;
        $operatingExpenses = 0.0;
        $expenseBreakdown = [];

        foreach ($expenseItems as $item) {
            $account = $allAccounts->get($item['account_id']);
            $isCogs = $account?->subtype?->name === 'Cost of Goods Sold' || $account?->code == '5010' || $account?->code == '5020' || $account?->code == '5030';

            $val = $item['balance']; // since it's normal debit balance, positive means net expense
            if ($isCogs) {
                $cogs += $val;
            } else {
                $operatingExpenses += $val;
                $expenseBreakdown[] = [
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'amount' => $val,
                ];
            }
        }

        $revenue = $revenueItems->sum('balance'); // normal credit balance

        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit - $operatingExpenses;

        return [
            'revenue' => round($revenue, 2),
            'revenue_items' => $revenueItems->map(fn ($r) => ['code' => $r['code'], 'name' => $r['name'], 'amount' => $r['balance']])->values()->all(),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'operating_expenses' => round($operatingExpenses, 2),
            'expense_items' => $expenseBreakdown,
            'net_profit' => round($netProfit, 2),
        ];
    }

    /**
     * Compute Balance Sheet
     */
    public function getBalanceSheet(Business $business, ?string $endDate = null): array
    {
        // For Balance Sheet, we want all transactions up to $endDate
        $trialBalance = $this->getTrialBalance($business, null, $endDate);

        $assets = $trialBalance->where('category', 'asset');
        $liabilities = $trialBalance->where('category', 'liability');
        $equity = $trialBalance->where('category', 'equity');

        $totalAssets = $assets->sum('balance'); // normal debit
        $totalLiabilities = $liabilities->sum('balance'); // normal credit
        $totalEquitySeeded = $equity->sum('balance'); // normal credit

        // Dynamic Profit/Loss calculation to represent Retained Earnings
        $pnl = $this->getProfitAndLoss($business, null, $endDate);
        $currentEarnings = $pnl['net_profit'];

        $totalEquity = $totalEquitySeeded + $currentEarnings;

        return [
            'assets' => $assets->map(fn ($r) => ['code' => $r['code'], 'name' => $r['name'], 'amount' => $r['balance']])->values()->all(),
            'total_assets' => round($totalAssets, 2),
            'liabilities' => $liabilities->map(fn ($r) => ['code' => $r['code'], 'name' => $r['name'], 'amount' => $r['balance']])->values()->all(),
            'total_liabilities' => round($totalLiabilities, 2),
            'equity' => $equity->map(fn ($r) => ['code' => $r['code'], 'name' => $r['name'], 'amount' => $r['balance']])->values()->all(),
            'current_earnings' => round($currentEarnings, 2),
            'total_equity' => round($totalEquity, 2),
            'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
        ];
    }
}
