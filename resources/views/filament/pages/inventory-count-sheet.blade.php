<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Search</span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Name, SKU, barcode"
                        class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                    >
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Category</span>
                    <select wire:model.live="categoryId" class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100">
                        <option value="">All categories</option>
                        @foreach ($this->categoryOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Vendor</span>
                    <select wire:model.live="vendorId" class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100">
                        <option value="">All vendors</option>
                        @foreach ($this->vendorOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Stock status</span>
                    <select wire:model.live="stockStatus" class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100">
                        <option value="all">All stock</option>
                        <option value="in_stock">In stock</option>
                        <option value="low_stock">Low stock</option>
                        <option value="out_of_stock">Out of stock</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total SKUs</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($this->summary['total_skus']) }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Items in stock</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($this->summary['items_in_stock']) }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Low stock items</div>
                <div class="mt-2 text-2xl font-semibold text-amber-600 dark:text-amber-400">{{ number_format($this->summary['low_stock_items']) }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Inventory valuation</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->formatMoney($this->summary['total_valuation']) }}</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Count Sheet Preview</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Download the PDF to print a blank units-in-store column for manual stock taking.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-gray-950">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Stock name</th>
                            <th class="px-4 py-3">System unit</th>
                            <th class="px-4 py-3 text-right">System qty</th>
                            <th class="px-4 py-3 text-center">Units in store</th>
                            <th class="px-4 py-3">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($this->rows as $row)
                            <tr>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['category'] ?? 'Unassigned' }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['unit_symbol'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format($row['system_quantity_display'], 2) }}</td>
                                <td class="px-4 py-3 text-center text-gray-400 dark:text-gray-500">________________</td>
                                <td class="px-4 py-3 text-gray-400 dark:text-gray-500">________________</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No products match the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
