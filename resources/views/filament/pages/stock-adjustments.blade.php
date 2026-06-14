<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Search</span>
                    <input
                        type="text"
                        wire:model.defer="search"
                        placeholder="Name, SKU, barcode"
                        class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                    >
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Category</span>
                    <select wire:model.defer="categoryId" class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100">
                        <option value="">All categories</option>
                        @foreach (app(\App\Services\InventoryCountSheetService::class)->categoryOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Vendor</span>
                    <select wire:model.defer="vendorId" class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100">
                        <option value="">All vendors</option>
                        @foreach (app(\App\Services\InventoryCountSheetService::class)->vendorOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Stock status</span>
                    <select wire:model.defer="stockStatus" class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100">
                        <option value="all">All stock</option>
                        <option value="in_stock">In stock</option>
                        <option value="low_stock">Low stock</option>
                        <option value="out_of_stock">Out of stock</option>
                    </select>
                </label>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <x-filament::button wire:click="loadLines" icon="heroicon-o-arrow-path">
                    Load products
                </x-filament::button>
                <x-filament::button color="gray" tag="a" href="{{ route('inventory.count-sheet.pdf', ['search' => $search, 'category_id' => $categoryId, 'vendor_id' => $vendorId, 'stock_status' => $stockStatus !== 'all' ? $stockStatus : null]) }}" target="_blank" icon="heroicon-o-arrow-down-tray">
                    Download count sheet
                </x-filament::button>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Counted at</span>
                    <input
                        type="date"
                        wire:model.defer="countedAt"
                        max="{{ now()->toDateString() }}"
                        class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                    >
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Reference</span>
                    <input
                        type="text"
                        wire:model.defer="reference"
                        placeholder="Optional internal reference"
                        class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                    >
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Posting mode</span>
                    <select wire:model.defer="postingMode" class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100">
                        <option value="inventory_only">Inventory only</option>
                        <option value="inventory_and_daybook">Inventory + daybook</option>
                    </select>
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Variance reason</span>
                    <input
                        type="text"
                        wire:model.defer="varianceReason"
                        placeholder="Optional reason"
                        class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                    >
                </label>
            </div>

            <label class="mt-4 block space-y-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Batch notes</span>
                <textarea
                    wire:model.defer="notes"
                    rows="3"
                    class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                ></textarea>
            </label>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Bulk stock adjustment</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter the counted quantity in the same unit shown on the count sheet. Leave a line blank to skip it.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-gray-950">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Unit</th>
                            <th class="px-4 py-3 text-right">System qty</th>
                            <th class="px-4 py-3 text-right">Counted qty</th>
                            <th class="px-4 py-3">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($lines as $productId => $line)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $line['name'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $line['category'] ?? 'Unassigned' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $line['unit_label'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format((float) $line['system_quantity_display'], 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        wire:model.live.debounce.500ms="lines.{{ $productId }}.counted_quantity"
                                        class="w-32 rounded-lg border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.500ms="lines.{{ $productId }}.notes"
                                        class="w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Load products to begin a stock count.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-4 py-3 dark:border-white/10">
                <x-filament::button wire:click="saveAdjustment" icon="heroicon-o-check">
                    Post stock adjustment
                </x-filament::button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Recent batches</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-gray-950">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3">Counted at</th>
                            <th class="px-4 py-3">Mode</th>
                            <th class="px-4 py-3 text-right">Variance value</th>
                            <th class="px-4 py-3">Counted by</th>
                            <th class="px-4 py-3">Posted by</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($this->recentBatches as $batch)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $batch->reference }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ optional($batch->counted_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ str_replace('_', ' ', $batch->posting_mode) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ config('retail.currency.symbol', 'रू') . ' ' . number_format((float) $batch->variance_value, 2) }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $batch->countedBy?->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $batch->postedBy?->name ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No stock adjustment batches yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
