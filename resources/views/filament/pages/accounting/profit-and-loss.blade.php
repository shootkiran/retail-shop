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

        <!-- Profit & Loss Sheet -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 md:p-8">
            <div class="max-w-4xl mx-auto">
                <div class="text-center border-b border-gray-150 pb-6 mb-8 dark:border-white/10">
                    <h2 class="text-xl font-bold tracking-tight text-gray-800 dark:text-gray-100">Income Statement</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        For the period from {{ \Carbon\Carbon::parse($this->startDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($this->endDate)->format('d M Y') }}
                    </p>
                </div>

                <!-- Revenue Section -->
                <div class="mb-8">
                    <div class="flex justify-between items-center font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 pb-2 dark:border-white/10">
                        <span class="text-base uppercase tracking-wider">Revenue</span>
                        <span class="font-mono text-base">{{ $this->formatMoney($this->pnlData['revenue']) }}</span>
                    </div>
                    <div class="mt-2 divide-y divide-gray-100 dark:divide-white/5 pl-4">
                        @forelse($this->pnlData['revenue_items'] as $item)
                            <div class="flex justify-between items-center py-2 text-sm text-gray-600 dark:text-gray-400">
                                <span>{{ $item['code'] }} — {{ $item['name'] }}</span>
                                <span class="font-mono">{{ $this->formatMoney($item['amount']) }}</span>
                            </div>
                        @empty
                            <div class="py-2 text-sm italic text-gray-400">No revenue accounts posted.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Cost of Goods Sold Section -->
                <div class="mb-8">
                    <div class="flex justify-between items-center font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 pb-2 dark:border-white/10">
                        <span class="text-base uppercase tracking-wider">Cost of Goods Sold (COGS)</span>
                        <span class="font-mono text-base text-rose-600 dark:text-rose-400">({{ $this->formatMoney($this->pnlData['cogs']) }})</span>
                    </div>
                </div>

                <!-- Gross Profit Section -->
                <div class="mb-8 bg-gray-50 dark:bg-white/5 p-4 rounded-lg flex justify-between items-center font-bold text-gray-900 dark:text-gray-100 border-l-4 border-primary-500">
                    <span class="text-base uppercase tracking-wider">Gross Profit</span>
                    <span class="font-mono text-lg">{{ $this->formatMoney($this->pnlData['gross_profit']) }}</span>
                </div>

                <!-- Operating Expenses Section -->
                <div class="mb-8">
                    <div class="flex justify-between items-center font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 pb-2 dark:border-white/10">
                        <span class="text-base uppercase tracking-wider">Operating Expenses</span>
                        <span class="font-mono text-base text-rose-600 dark:text-rose-400">({{ $this->formatMoney($this->pnlData['operating_expenses']) }})</span>
                    </div>
                    <div class="mt-2 divide-y divide-gray-100 dark:divide-white/5 pl-4">
                        @forelse($this->pnlData['expense_items'] as $item)
                            <div class="flex justify-between items-center py-2 text-sm text-gray-600 dark:text-gray-400">
                                <span>{{ $item['code'] }} — {{ $item['name'] }}</span>
                                <span class="font-mono">{{ $this->formatMoney($item['amount']) }}</span>
                            </div>
                        @empty
                            <div class="py-2 text-sm italic text-gray-400">No operating expense accounts posted.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Net Profit Section -->
                <div class="border-t-2 border-gray-300 dark:border-white/20 pt-6 mt-8 flex justify-between items-center font-bold">
                    <span class="text-lg uppercase tracking-wider text-gray-900 dark:text-gray-100">Net Profit / Income</span>
                    <span class="font-mono text-2xl {{ $this->pnlData['net_profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        {{ $this->formatMoney($this->pnlData['net_profit']) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
