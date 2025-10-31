@push('styles')
    <style>
        .pos-compact,
        .pos-compact * {
            font-size: 0.625rem !important;
            line-height: 1.1;
        }

        .pos-compact .gap-6,
        .pos-compact .gap-5,
        .pos-compact .gap-4,
        .pos-compact .gap-3,
        .pos-compact .gap-2,
        .pos-compact .gap-1 {
            gap: 0.25rem !important;
        }

        .pos-compact .space-y-4 > :not([hidden]) ~ :not([hidden]),
        .pos-compact .space-y-3 > :not([hidden]) ~ :not([hidden]),
        .pos-compact .space-y-2\.5 > :not([hidden]) ~ :not([hidden]),
        .pos-compact .space-y-2 > :not([hidden]) ~ :not([hidden]),
        .pos-compact .space-y-1 > :not([hidden]) ~ :not([hidden]) {
            margin-top: 0.25rem !important;
        }

        .pos-compact .px-4 {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }

        .pos-compact .py-3 {
            padding-top: 0.25rem !important;
            padding-bottom: 0.25rem !important;
        }

        .pos-compact .py-6 {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }

        .pos-compact .p-4 {
            padding: 0.5rem !important;
        }

        .pos-compact table th,
        .pos-compact table td {
            padding: 0.25rem 0.35rem !important;
        }

        .pos-compact input,
        .pos-compact select,
        .pos-compact textarea,
        .pos-compact .fi-input,
        .pos-compact .fi-select,
        .pos-compact button,
        .pos-compact .fi-button {
            padding: 0.25rem 0.4rem !important;
            min-height: auto !important;
        }
    </style>
@endpush

<x-filament-panels::page>
    <div
        class="pos-compact"
        x-data
        x-init="
            const handler = (event) => {
                if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    $wire.clearCart();
                }
            };
            window.addEventListener('keydown', handler);
            return () => window.removeEventListener('keydown', handler);
        "
    >
        <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <x-filament::section>
                <x-slot name="heading">Quick Actions</x-slot>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <x-filament::input.wrapper label="Search Products">
                        <x-filament::input
                            type="search"
                            wire:model.live.debounce.400ms="search"
                            placeholder="Search by name, SKU or barcode"
                            autocomplete="off"
                        />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper label="Scan Barcode">
                        <x-filament::input
                            type="text"
                            wire:model.defer="barcode"
                            placeholder="Scan or enter barcode"
                            autocomplete="off"
                            wire:keydown.enter="scanBarcode"
                        />
                    </x-filament::input.wrapper>

                    <div class="flex items-end">
                        <button
                            type="button"
                            wire:click="holdOrder"
                            wire:loading.attr="disabled"
                            class="flex w-full items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-gray-100 px-3 py-2 font-semibold text-gray-700 transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-60 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
                        >
                            <x-heroicon-o-clock class="h-3.5 w-3.5" />
                            <span>Hold Order</span>
                        </button>
                    </div>
                </div>
            </x-filament::section>

            @if ($this->heldOrders->isNotEmpty())
                <x-filament::section>
                    <x-slot name="heading">Held Orders</x-slot>

                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10 text-sm">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr class="text-left">
                                    <th class="px-4 py-3 font-medium">Label</th>
                                    <th class="px-4 py-3 font-medium">Customer</th>
                                    <th class="px-4 py-3 font-medium">Cart Preview</th>
                                    <th class="px-4 py-3 font-medium">Updated</th>
                                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($this->heldOrders as $heldOrder)
                                    @php
                                        $items = collect(data_get($heldOrder->cart, 'items', []));
                                        $previewItems = $items->take(3)->map(function ($item) {
                                            $quantity = (int) ($item['quantity'] ?? 1);
                                            $quantity = $quantity > 0 ? $quantity : 1;
                                            $name = $item['name'] ?? 'Item';
                                            return $quantity . ' x ' . $name;
                                        })->implode(', ');
                                        $remainingCount = max($items->count() - 3, 0);
                                        $preview = $previewItems !== '' ? $previewItems : 'No items';
                                        if ($remainingCount > 0) {
                                            $preview .= ' +' . $remainingCount . ' more';
                                        }
                                    @endphp
                                    <tr wire:key="held-order-{{ $heldOrder->id }}">
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-medium">{{ $heldOrder->label ?? 'Untitled order' }}</div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            {{ $heldOrder->customer?->name ?? 'Walk-in customer' }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm text-gray-600 dark:text-gray-300">
                                            {{ $preview }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm text-gray-600 dark:text-gray-300">
                                            {{ $heldOrder->updated_at->diffForHumans() }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-right">
                                            <button
                                                type="button"
                                                wire:click="resumeOrder({{ $heldOrder->id }})"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center gap-1.5 rounded-md bg-primary-600 px-2.5 py-1.5 font-semibold text-white transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-60"
                                            >
                                                <x-heroicon-o-arrow-path class="h-3 w-3" />
                                                <span>Resume</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif

            <x-filament::section>
                <x-slot name="heading">Categories</x-slot>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        wire:click="selectCategory(null)"
                        class="@class([
                            'inline-flex items-center rounded-md border px-3 py-1 font-semibold transition focus:outline-none focus:ring-2 focus:ring-primary-500',
                            'border-primary-600 bg-primary-600 text-white hover:bg-primary-500' => ! $activeCategory,
                            'border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700' => $activeCategory,
                        ])"
                    >
                        All Products
                    </button>

                    @foreach ($this->categories as $category)
                        <button
                            type="button"
                            wire:click="selectCategory({{ $category->id }})"
                            class="@class([
                                'inline-flex items-center rounded-md border px-3 py-1 font-semibold transition focus:outline-none focus:ring-2 focus:ring-primary-500',
                                'border-primary-600 bg-primary-600 text-white hover:bg-primary-500' => $activeCategory === $category->id,
                                'border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700' => $activeCategory !== $category->id,
                            ])"
                        >
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>
            </x-filament::section>

            <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                @forelse ($this->products as $product)
                    <x-filament::section wire:key="product-{{ $product->id }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $product->name }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    SKU: {{ $product->sku }}
                                    @if ($product->barcode)
                                        &bull; Barcode: {{ $product->barcode }}
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    In stock: {{ $product->stock_quantity }}
                                </p>
                            </div>
                            <x-filament::badge color="success">₦{{ number_format($product->unit_price, 2) }}</x-filament::badge>
                        </div>

                        <button
                            type="button"
                            wire:click="addProduct({{ $product->id }})"
                            wire:loading.attr="disabled"
                            class="mt-4 flex w-full items-center justify-center gap-1.5 rounded-md bg-primary-600 px-3 py-2 font-semibold text-white transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-60"
                        >
                            <x-heroicon-o-plus class="h-3.5 w-3.5" />
                            <span>Add to Cart</span>
                        </button>
                    </x-filament::section>
                @empty
                    <x-filament::empty-state class="sm:col-span-2 2xl:col-span-3" icon="heroicon-o-magnifying-glass">
                        <x-slot name="heading">No products found</x-slot>
                        <x-slot name="description">
                            Try adjusting your filters or add new inventory from the Catalog section.
                        </x-slot>
                    </x-filament::empty-state>
                @endforelse
            </div>
        </div>

        <div class="space-y-4">
            <x-filament::section>
                <x-slot name="heading">Customer &amp; Payment</x-slot>

                <div class="space-y-4">
                    <x-filament::input.wrapper label="Customer">
                        <select
                            wire:model="customerId"
                            class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">Walk-in customer</option>
                            @foreach ($this->customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper label="Payment Method">
                        <select
                            wire:model="paymentMethodId"
                            class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">Select a method</option>
                            @foreach ($this->paymentMethods as $method)
                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper label="Payment Type">
                        <select
                            wire:model="paymentType"
                            class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="paid">Paid</option>
                            <option value="credit">Credit</option>
                        </select>
                    </x-filament::input.wrapper>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Cart</x-slot>

                <div class="space-y-3">
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10 text-sm">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr class="text-left">
                                    <th class="px-4 py-3 font-medium">Item</th>
                                    <th class="px-4 py-3 font-medium">Qty</th>
                                    <th class="px-4 py-3 font-medium">Discount</th>
                                    <th class="px-4 py-3 font-medium text-right">Total</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                @forelse ($this->cart as $rowKey => $item)
                                    <tr wire:key="cart-{{ $rowKey }}">
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $item['name'] }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $item['sku'] ?? 'N/A' }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-filament::input
                                                type="number"
                                                min="1"
                                                wire:model.live="cart.{{ $rowKey }}.quantity"
                                                class="w-20"
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-filament::input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                wire:model.live="cart.{{ $rowKey }}.discount"
                                                class="w-24"
                                            />
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold">
                                            ₦{{ number_format($item['line_total'] ?? (($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0)), 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                wire:click="removeItem('{{ $rowKey }}')"
                                                class="rounded-md p-1.5 text-red-500 transition hover:bg-red-50 focus:outline-none focus:ring-1 focus:ring-red-400 dark:text-red-400 dark:hover:bg-red-500/10"
                                            >
                                                <span class="sr-only">Remove</span>
                                                <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No items in the cart yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-filament::input.wrapper label="Order Discount">
                            <x-filament::input
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.live="orderDiscount"
                                prefix="₦"
                            />
                        </x-filament::input.wrapper>

                        <x-filament::input.wrapper label="Amount Paid">
                            <x-filament::input
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.live="amountPaid"
                                prefix="₦"
                            />
                        </x-filament::input.wrapper>

                        <div class="rounded-lg bg-gray-50 p-4 text-sm dark:bg-white/5 sm:col-span-2">
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                                <span class="font-semibold">₦{{ number_format($this->subtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Total Discounts</span>
                                <span class="font-semibold">₦{{ number_format($this->lineDiscount + $orderDiscount, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Subtotal After Discount</span>
                                <span class="font-semibold">₦{{ number_format($this->subtotalAfterDiscount, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Tax</span>
                                <span class="font-semibold">₦{{ number_format($this->taxAmount, 2) }}</span>
                            </div>
                            <div class="mt-3 flex justify-between text-base font-semibold">
                                <span>Total Due</span>
                                <span>₦{{ number_format($this->grandTotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
                                <span>Balance</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">₦{{ number_format($this->amountDue, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2.5">
                        <button
                            type="button"
                            wire:click="checkout"
                            wire:loading.attr="disabled"
                            class="flex w-full items-center justify-center gap-1.5 rounded-md bg-emerald-600 px-3 py-2 font-semibold text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-60"
                        >
                            <x-heroicon-o-credit-card class="h-3.5 w-3.5" />
                            <span>Checkout &amp; Print Invoice</span>
                        </button>

                        <button
                            type="button"
                            wire:click="clearCart"
                            class="flex w-full items-center justify-center gap-1.5 rounded-md border border-red-300 bg-red-50 px-3 py-2 font-semibold text-red-600 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-400 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20"
                        >
                            <x-heroicon-o-trash class="h-3.5 w-3.5" />
                            <span>Clear Cart</span>
                        </button>

                        @if ($lastSaleId)
                            <a
                                href="{{ route('sales.invoice', $lastSaleId) }}"
                                target="_blank"
                                class="flex w-full items-center justify-center gap-1.5 rounded-md border border-secondary-500 bg-secondary-600 px-3 py-2 font-semibold text-white transition hover:bg-secondary-500 focus:outline-none focus:ring-2 focus:ring-secondary-400"
                            >
                                <x-heroicon-o-document-text class="h-3.5 w-3.5" />
                                <span>Download Last Invoice</span>
                            </a>
                        @endif
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Recent Sales</x-slot>

                <div class="space-y-2.5">
                    @forelse ($this->recentSales as $sale)
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $sale->reference }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ optional($sale->sold_at)->diffForHumans() ?? 'Not set' }}
                                </div>
                            </div>
                            <div class="text-sm font-semibold">₦{{ number_format($sale->grand_total, 2) }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No sales recorded yet.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    </div>
    </div>
</x-filament-panels::page>
