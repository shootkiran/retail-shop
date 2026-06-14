<x-filament-panels::page>
    <div class="flex flex-col gap-y-6">
        <!-- Date filters -->
        <div class="p-4 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex flex-wrap items-end gap-4">
            <div>
                <label for="startDate" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Start Date</label>
                <input type="date" id="startDate" wire:model.live="startDate" class="rounded-lg border border-gray-300 dark:border-white/10 dark:bg-gray-800 text-sm py-2 px-3 text-gray-800 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500">
            </div>
            <div>
                <label for="endDate" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">End Date</label>
                <input type="date" id="endDate" wire:model.live="endDate" class="rounded-lg border border-gray-300 dark:border-white/10 dark:bg-gray-800 text-sm py-2 px-3 text-gray-800 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500">
            </div>
        </div>

        <!-- Trial Balance Table -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-6 py-4">Account Code</th>
                            <th class="px-6 py-4">Account Name</th>
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4 text-right">Debit</th>
                            <th class="px-6 py-4 text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @forelse($this->trialBalanceRows as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-6 py-4 font-mono text-gray-900 dark:text-gray-100">
                                    {{ $row['code'] }}
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-800 dark:text-gray-200">
                                    {{ $row['name'] }}
                                </td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400 uppercase text-xs tracking-wider">
                                    {{ $row['category'] }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-gray-800 dark:text-gray-200">
                                    {{ $row['debit'] > 0 ? $this->formatMoney($row['debit']) : '—' }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-gray-800 dark:text-gray-200">
                                    {{ $row['credit'] > 0 ? $this->formatMoney($row['credit']) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center italic text-gray-500 dark:text-gray-400">
                                    No financial entries found for the selected date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-white/5 font-semibold">
                        <tr class="text-gray-800 dark:text-gray-200 border-t-2 border-gray-300 dark:border-white/20">
                            <td colspan="3" class="px-6 py-4 text-left">Total</td>
                            <td class="px-6 py-4 text-right font-mono text-lg text-emerald-600 dark:text-emerald-400">
                                {{ $this->formatMoney($this->getDebitTotal()) }}
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-lg text-emerald-600 dark:text-emerald-400">
                                {{ $this->formatMoney($this->getCreditTotal()) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if(abs($this->getDebitTotal() - $this->getCreditTotal()) > 0.01)
            <div class="p-4 rounded-xl bg-danger-50 dark:bg-danger-950/20 text-danger-600 dark:text-danger-400 text-sm font-semibold flex items-center gap-2 border border-danger-200 dark:border-danger-900/50">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                <span>Ledger is Out of Balance! Difference: {{ $this->formatMoney(abs($this->getDebitTotal() - $this->getCreditTotal())) }}</span>
            </div>
        @else
            <div class="p-4 rounded-xl bg-success-50 dark:bg-success-950/20 text-success-600 dark:text-success-400 text-sm font-semibold flex items-center gap-2 border border-success-200 dark:border-success-900/50">
                <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" />
                <span>General Ledger balances are mathematically correct.</span>
            </div>
        @endif
    </div>
</x-filament-panels::page>
