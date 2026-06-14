<x-filament-panels::page>
    <div class="flex flex-col gap-y-6">
        <!-- Date filter -->
        <div class="p-4 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex flex-wrap items-end gap-4">
            <div>
                <label for="endDate" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">As of Date</label>
                <input type="date" id="endDate" wire:model.live="endDate" class="rounded-lg border border-gray-300 dark:border-white/10 dark:bg-gray-800 text-sm py-2 px-3 text-gray-800 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500">
            </div>
        </div>

        <!-- Balance Sheet Container -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 md:p-8">
            <div class="max-w-4xl mx-auto">
                <div class="text-center border-b border-gray-150 pb-6 mb-8 dark:border-white/10">
                    <h2 class="text-xl font-bold tracking-tight text-gray-800 dark:text-gray-100">Statement of Financial Position</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        As of {{ \Carbon\Carbon::parse($this->endDate)->format('d M Y') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-16">
                    <!-- Left Column: Assets -->
                    <div>
                        <div class="flex justify-between items-center font-bold text-gray-950 dark:text-gray-100 border-b-2 border-gray-300 pb-2 dark:border-white/20">
                            <span class="text-base uppercase tracking-wider">Assets</span>
                        </div>
                        <div class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                            @forelse($this->balanceSheetData['assets'] as $asset)
                                <div class="flex justify-between items-center py-2 text-sm text-gray-600 dark:text-gray-400">
                                    <span>{{ $asset['code'] }} — {{ $asset['name'] }}</span>
                                    <span class="font-mono">{{ $this->formatMoney($asset['amount']) }}</span>
                                </div>
                            @empty
                                <div class="py-2 text-sm italic text-gray-400">No assets recorded.</div>
                            @endforelse
                        </div>
                        <div class="mt-6 bg-gray-50 dark:bg-white/5 p-4 rounded-lg flex justify-between items-center font-bold text-gray-900 dark:text-gray-100 border-l-4 border-emerald-500">
                            <span class="text-sm uppercase tracking-wider">Total Assets</span>
                            <span class="font-mono text-base">{{ $this->formatMoney($this->balanceSheetData['total_assets']) }}</span>
                        </div>
                    </div>

                    <!-- Right Column: Liabilities and Equity -->
                    <div>
                        <!-- Liabilities Section -->
                        <div class="mb-8">
                            <div class="flex justify-between items-center font-bold text-gray-950 dark:text-gray-100 border-b-2 border-gray-300 pb-2 dark:border-white/20">
                                <span class="text-base uppercase tracking-wider">Liabilities</span>
                            </div>
                            <div class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                                @forelse($this->balanceSheetData['liabilities'] as $liability)
                                    <div class="flex justify-between items-center py-2 text-sm text-gray-600 dark:text-gray-400">
                                        <span>{{ $liability['code'] }} — {{ $liability['name'] }}</span>
                                        <span class="font-mono">{{ $this->formatMoney($liability['amount']) }}</span>
                                    </div>
                                @empty
                                    <div class="py-2 text-sm italic text-gray-400">No liabilities recorded.</div>
                                @endforelse
                            </div>
                            <div class="mt-4 flex justify-between font-bold text-sm text-gray-700 dark:text-gray-300 px-2">
                                <span>Total Liabilities</span>
                                <span class="font-mono">{{ $this->formatMoney($this->balanceSheetData['total_liabilities']) }}</span>
                            </div>
                        </div>

                        <!-- Equity Section -->
                        <div>
                            <div class="flex justify-between items-center font-bold text-gray-950 dark:text-gray-100 border-b-2 border-gray-300 pb-2 dark:border-white/20">
                                <span class="text-base uppercase tracking-wider">Equity</span>
                            </div>
                            <div class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                                @foreach($this->balanceSheetData['equity'] as $eq)
                                    <div class="flex justify-between items-center py-2 text-sm text-gray-600 dark:text-gray-400">
                                        <span>{{ $eq['code'] }} — {{ $eq['name'] }}</span>
                                        <span class="font-mono">{{ $this->formatMoney($eq['amount']) }}</span>
                                    </div>
                                @endforeach
                                <div class="flex justify-between items-center py-2 text-sm text-gray-600 dark:text-gray-400 font-medium">
                                    <span>Current Period Earnings</span>
                                    <span class="font-mono">{{ $this->formatMoney($this->balanceSheetData['current_earnings']) }}</span>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-between font-bold text-sm text-gray-700 dark:text-gray-300 px-2">
                                <span>Total Equity</span>
                                <span class="font-mono">{{ $this->formatMoney($this->balanceSheetData['total_equity']) }}</span>
                            </div>
                        </div>

                        <div class="mt-6 bg-gray-50 dark:bg-white/5 p-4 rounded-lg flex justify-between items-center font-bold text-gray-900 dark:text-gray-100 border-l-4 border-emerald-500">
                            <span class="text-sm uppercase tracking-wider">Total Liabilities & Equity</span>
                            <span class="font-mono text-base">{{ $this->formatMoney($this->balanceSheetData['total_liabilities_and_equity']) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Balance Check -->
                @if(abs($this->balanceSheetData['total_assets'] - $this->balanceSheetData['total_liabilities_and_equity']) > 0.01)
                    <div class="mt-8 p-4 rounded-xl bg-danger-50 dark:bg-danger-950/20 text-danger-600 dark:text-danger-400 text-sm font-semibold flex items-center gap-2 border border-danger-200 dark:border-danger-900/50">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                        <span>Balance Sheet is out of balance! Difference: {{ $this->formatMoney(abs($this->balanceSheetData['total_assets'] - $this->balanceSheetData['total_liabilities_and_equity'])) }}</span>
                    </div>
                @else
                    <div class="mt-8 p-4 rounded-xl bg-success-50 dark:bg-success-950/20 text-success-600 dark:text-success-400 text-sm font-semibold flex items-center gap-2 border border-success-200 dark:border-success-900/50">
                        <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" />
                        <span>Accounting Equation is balanced (Assets = Liabilities + Equity).</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
