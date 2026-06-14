<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Date</span>
                    <input
                        type="date"
                        max="{{ now()->toDateString() }}"
                        wire:model.live="day"
                        class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                    >
                </label>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-950">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Opening balance</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $this->formatMoney($this->getOpeningBalance()) }}
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-950">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Closing balance</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $this->formatMoney($this->getClosingBalance()) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Expenses</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Particulars</th>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @forelse ($this->getExpenseRows() as $row)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $this->formatDate($row['date']) }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                        <div>{{ $row['particulars'] }}</div>
                                        @if ($row['note'])
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['note'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $row['reference'] ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right font-medium text-gray-900 dark:text-gray-100">
                                        {{ $this->formatMoney($row['amount']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No expenses for this date.
                                    </td>
                                </tr>
                            @endforelse

                            <tr class="bg-gray-50 font-semibold dark:bg-gray-950">
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300"></td>
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">Balance c/d</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                    {{ $this->formatMoney($this->getClosingBalance()) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Incomes</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Particulars</th>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            <tr class="bg-gray-50 font-semibold dark:bg-gray-950">
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300"></td>
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">Balance b/d</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                    {{ $this->formatMoney($this->getOpeningBalance()) }}
                                </td>
                            </tr>

                            @forelse ($this->getIncomeRows() as $row)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $this->formatDate($row['date']) }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                        <div>{{ $row['particulars'] }}</div>
                                        @if ($row['note'])
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['note'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $row['reference'] ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right font-medium text-gray-900 dark:text-gray-100">
                                        {{ $this->formatMoney($row['amount']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No incomes for this date.
                                    </td>
                                </tr>
                            @endforelse

                            <tr class="bg-gray-50 font-semibold dark:bg-gray-950">
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300"></td>
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">Balance c/d</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                    {{ $this->formatMoney($this->getClosingBalance()) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
