<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-filament::section>
                <x-slot name="heading">Quick Actions</x-slot>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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

                    <x-filament::input.wrapper label="Hold Label">
                        <x-filament::input
                            type="text"
                            wire:model.defer="holdName"
                            placeholder="Optional hold reference"
                        />
                    </x-filament::input.wrapper>

                    <div class="flex items-end gap-3">
                        <x-filament::button
                            color="gray"
                            icon="heroicon-o-clock"
                            wire:click="holdOrder"
                            wire:loading.attr="disabled"
                            class="w-full"
                        >
                            Hold Order
                        </x-filament::button>

                        <x-filament::dropdown>
                            <x-slot name="trigger">
                                <x-filament::button color="gray" icon="heroicon-o-arrow-path" type="button">
                                    Resume Order
                                </x-filament::button>
                            </x-slot>

                            <x-filament::dropdown.list>
                                @forelse ($this->heldOrders as $heldOrder)
                                    <x-filament::dropdown.list.item
                                        wire:click="resumeOrder({{ $heldOrder->id }})"
                                        tag="button"
                                    >
                                        <span class="flex flex-col text-start">
                                            <span class="font-semibold text-sm">{{ $heldOrder->label }}</span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                Updated {{ $heldOrder->updated_at->diffForHumans() }}
                                            </span>
                                        </span>
                                    </x-filament::dropdown.list.item>
                                @empty
                                    <x-filament::dropdown.list.item disabled>
                                        No held orders
                                    </x-filament::dropdown.list.item>
                                @endforelse
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Categories</x-slot>

                <div class="flex flex-wrap gap-3">
                    <x-filament::button
                        size="sm"
                        color="{{ $activeCategory ? 'gray' : 'primary' }}"
                        wire:click="selectCategory(null)"
                        type="button"
                    >
                        All Products
                    </x-filament::button>

                    @foreach ($this->categories as $category)
                        <x-filament::button
                            size="sm"
                            color="{{ $activeCategory === $category->id ? 'primary' : 'gray' }}"
                            wire:click="selectCategory({{ $category->id }})"
                            type="button"
                        >
                            {{ $category->name }}
                        </x-filament::button>
                    @endforeach
                </div>
            </x-filament::section>

            <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
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

                        <x-filament::button
                            class="mt-4"
                            color="primary"
                            icon="heroicon-o-plus"
                            full
                            wire:click="addProduct({{ $product->id }})"
                            wire:loading.attr="disabled"
                        >
                            Add to Cart
                        </x-filament::button>
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

        <div class="space-y-6">
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

                <div class="space-y-4">
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
                                            <x-filament::icon-button
                                                color="danger"
                                                icon="heroicon-o-trash"
                                                wire:click="removeItem('{{ $rowKey }}')"
                                            />
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

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-filament::input.wrapper label="Order Discount">
                            <x-filament::input
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.live="orderDiscount"
                                prefix="₦"
                            />
                        </x-filament::input.wrapper>

                        <x-filament::input.wrapper label="Tax Rate">
                            <x-filament::input
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.live="taxRate"
                                suffix="%"
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

                        <div class="rounded-lg bg-gray-50 p-4 text-sm dark:bg-white/5">
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                                <span class="font-semibold">₦{{ number_format($this->subtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Discounts</span>
                                <span class="font-semibold">₦{{ number_format($this->lineDiscount + $orderDiscount, 2) }}</span>
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

                    <div class="space-y-3">
                        <x-filament::button
                            color="success"
                            icon="heroicon-o-credit-card"
                            full
                            wire:click="checkout"
                            wire:loading.attr="disabled"
                        >
                            Checkout &amp; Print Invoice
                        </x-filament::button>

                        <x-filament::button
                            color="gray"
                            icon="heroicon-o-trash"
                            full
                            wire:click="clearCart"
                        >
                            Clear Cart
                        </x-filament::button>

                        @if ($lastSaleId)
                            <x-filament::button
                                tag="a"
                                :href="route('sales.invoice', $lastSaleId)"
                                target="_blank"
                                color="secondary"
                                icon="heroicon-o-document-text"
                                full
                            >
                                Download Last Invoice
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Recent Sales</x-slot>

                <div class="space-y-3">
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
</x-filament-panels::page>
