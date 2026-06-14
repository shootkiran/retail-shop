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

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Tax Output -->
            <div class="p-6 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex flex-col justify-between border-l-4 border-rose-500">
                <div>
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tax Output (Sales collected)</span>
                    <h3 class="text-2xl font-bold font-mono text-gray-800 dark:text-gray-100 mt-2">
                        {{ $this->formatMoney($this->taxData['tax_output']) }}
                    </h3>
                </div>
                <p class="text-xs text-gray-400 mt-3">Tax collected on invoices and POS receipts.</p>
            </div>

            <!-- Tax Input -->
            <div class="p-6 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex flex-col justify-between border-l-4 border-emerald-500">
                <div>
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tax Input (Purchases paid)</span>
                    <h3 class="text-2xl font-bold font-mono text-gray-800 dark:text-gray-100 mt-2">
                        {{ $this->formatMoney($this->taxData['tax_input']) }}
                    </h3>
                </div>
                <p class="text-xs text-gray-400 mt-3">Tax paid on purchases and vendor bills.</p>
            </div>

            <!-- Net Payable -->
            <div class="p-6 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex flex-col justify-between border-l-4 border-primary-500">
                <div>
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Net Tax Payable / (Credit)</span>
                    <h3 class="text-2xl font-bold font-mono {{ $this->taxData['net_payable'] >= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }} mt-2">
                        {{ $this->formatMoney($this->taxData['net_payable']) }}
                    </h3>
                </div>
                <p class="text-xs text-gray-400 mt-3">
                    @if($this->taxData['net_payable'] >= 0)
                        Net amount due to be paid to tax authority.
                    @else
                        Tax credit balance due to carry forward.
                    @endif
                </p>
            </div>
        </div>

        <!-- Tax Details Breakdown Sheet -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 md:p-8">
            <div class="max-w-3xl mx-auto">
                <div class="text-center border-b border-gray-150 pb-6 mb-8 dark:border-white/10">
                    <h2 class="text-xl font-bold tracking-tight text-gray-800 dark:text-gray-100">Tax / VAT Return Summary</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Report period: {{ \Carbon\Carbon::parse($this->startDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($this->endDate)->format('d M Y') }}
                    </p>
                </div>

                <div class="space-y-6">
                    <div class="flex justify-between items-center text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-white/5">
                        <span>Tax Output Collected (Liabilities)</span>
                        <span class="font-mono">{{ $this->formatMoney($this->taxData['tax_output']) }}</span>
                    </div>

                    <div class="flex justify-between items-center text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-white/5">
                        <span>Less: Tax Input Paid (Credits)</span>
                        <span class="font-mono text-emerald-600 dark:text-emerald-400">- {{ $this->formatMoney($this->taxData['tax_input']) }}</span>
                    </div>

                    <div class="pt-6 border-t-2 border-gray-300 dark:border-white/20 flex justify-between items-center font-bold text-base">
                        <span class="text-gray-900 dark:text-gray-100">Net Tax Payable / Refundable</span>
                        <span class="font-mono text-xl {{ $this->taxData['net_payable'] >= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ $this->formatMoney($this->taxData['net_payable']) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
