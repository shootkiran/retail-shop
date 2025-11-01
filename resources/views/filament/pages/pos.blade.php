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
        .pos-compact button {
            padding: 0.25rem 0.4rem !important;
            min-height: auto !important;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
@endpush

<x-filament-panels::page>
    <div
        class="pos-compact"
        wire:ignore
        x-data="posApp(@js($this->frontendState))"
        x-init="init()"
        @keydown.escape.window="closeProductPreview()"
    >
        <div class="grid gap-4 xl:grid-cols-3">
            <div class="space-y-4 xl:col-span-2">
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">Quick Actions</h2>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <label class="flex flex-col gap-1 text-[0.6rem] font-semibold text-gray-600 dark:text-gray-300">
                            <span>Search Products</span>
                            <input
                                type="search"
                                x-model.debounce.300ms="filters.search"
                                placeholder="Search by name, SKU or barcode"
                                autocomplete="off"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-normal shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                            >
                        </label>

                        <label class="flex flex-col gap-1 text-[0.6rem] font-semibold text-gray-600 dark:text-gray-300">
                            <span>Scan Barcode</span>
                            <input
                                type="text"
                                x-model="scanner.input"
                                placeholder="Scan or enter barcode"
                                autocomplete="off"
                                @keydown.enter.prevent="applyBarcode()"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-normal shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                            >
                        </label>

                        <div class="flex items-end">
                            <button
                                type="button"
                                @click="holdCurrentOrder"
                                :disabled="isProcessing || cart.length === 0 || !canCheckout"
                                class="flex w-full items-center justify-center gap-1.5 rounded-md border border-gray-200 bg-gray-100 px-3 py-2 font-semibold text-gray-700 transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
                            >
                                <x-heroicon-o-clock class="h-3.5 w-3.5" />
                                <span>Hold Order</span>
                            </button>
                        </div>
                    </div>
                </section>

                <template x-if="heldOrders.length">
                    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">Held Orders</h2>
                            <span class="text-[0.55rem] font-medium text-gray-400">Resume to move items back into the cart</span>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10 text-xs">
                                <thead class="bg-gray-50 dark:bg-white/5">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-semibold">Label</th>
                                        <th class="px-3 py-2 font-semibold">Customer</th>
                                        <th class="px-3 py-2 font-semibold">Cart Preview</th>
                                        <th class="px-3 py-2 font-semibold">Updated</th>
                                        <th class="px-3 py-2 text-right font-semibold">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                    <template x-for="order in heldOrders" :key="order.id">
                                        <tr>
                                            <td class="px-3 py-2 align-top">
                                                <div class="font-medium text-gray-900 dark:text-gray-100" x-text="order.label"></div>
                                            </td>
                                            <td class="px-3 py-2 align-top" x-text="order.customer_name"></td>
                                            <td class="px-3 py-2 align-top text-gray-600 dark:text-gray-300" x-text="order.preview"></td>
                                            <td class="px-3 py-2 align-top text-gray-600 dark:text-gray-300" x-text="order.updated_at_for_humans"></td>
                                            <td class="px-3 py-2 align-top text-right">
                                                <div class="flex justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        @click="resumeHeldOrder(order.id)"
                                                        :disabled="isProcessing"
                                                        class="inline-flex items-center gap-1.5 rounded-md bg-primary-600 px-2.5 py-1.5 font-semibold text-white transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                                    >
                                                        <x-heroicon-o-arrow-path class="h-3 w-3" />
                                                        <span>Resume</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="deleteHeldOrder(order.id)"
                                                        :disabled="isProcessing"
                                                        class="inline-flex items-center gap-1 rounded-md border border-red-300 px-2 py-1 text-[0.65rem] font-semibold text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-400 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10"
                                                    >
                                                        <x-heroicon-o-trash class="h-3 w-3" />
                                                        <span>Delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </template>

                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">Categories</h2>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            @click="filters.activeCategory = null"
                            :class="filters.activeCategory === null ? 'border-primary-600 bg-primary-600 text-white hover:bg-primary-500' : 'border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700'"
                            class="inline-flex items-center rounded-md border px-3 py-1 text-[0.7rem] font-semibold transition focus:outline-none focus:ring-2 focus:ring-primary-500"
                        >
                            All Products
                        </button>

                        <template x-for="category in categories" :key="category.id">
                            <button
                                type="button"
                                @click="filters.activeCategory = category.id"
                                :class="filters.activeCategory === category.id ? 'border-primary-600 bg-primary-600 text-white hover:bg-primary-500' : 'border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700'"
                                class="inline-flex items-center rounded-md border px-3 py-1 text-[0.7rem] font-semibold transition focus:outline-none focus:ring-2 focus:ring-primary-500"
                                x-text="category.name"
                            ></button>
                        </template>
                    </div>
                </section>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <template x-if="filteredProducts.length === 0">
                        <div class="sm:col-span-2 lg:col-span-4 rounded-xl border border-dashed border-gray-200 p-6 text-center text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                            No products match your filters.
                        </div>
                    </template>

                    <template x-for="product in filteredProducts" :key="product.id">
                        <section class="relative space-y-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm transition hover:border-primary-200 dark:border-white/10 dark:bg-gray-900">
                            <span class="absolute right-3 top-3 rounded-full bg-emerald-100 px-2 py-0.5 text-xl font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300" x-text="formatMoney(product.unit_price)"></span>
                            <button
                                type="button"
                                x-show="product.image_url"
                                x-cloak
                                @click.stop="openProductPreview(product)"
                                :title="'Quick view ' + (product.name ?? 'product')"
                                class="absolute left-3 top-3 flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 bg-white/90 text-gray-600 transition hover:text-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-800/90 dark:text-gray-200"
                            >
                                <x-heroicon-o-eye class="h-4 w-4" />
                            </button>
                            <div class="space-y-1 pr-12 pt-6">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="product.name"></h3>
                                <p class="text-[0.6rem] text-gray-500 dark:text-gray-400" x-text="'SKU: ' + (product.sku ?? 'N/A')"></p>
                                <template x-if="product.barcode">
                                    <p class="text-[0.6rem] text-gray-500 dark:text-gray-400" x-text="'Barcode: ' + product.barcode"></p>
                                </template>
                                <p class="text-[0.6rem] text-gray-500 dark:text-gray-400" x-text="'In stock: ' + product.stock_quantity"></p>
                            </div>

                            <button
                                type="button"
                                @click="addToCart(product)"
                                :disabled="isProcessing"
                                class="flex w-full items-center justify-center gap-1.5 rounded-md bg-primary-600 px-3 py-2 text-[0.7rem] font-semibold text-white transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <x-heroicon-o-plus class="h-3.5 w-3.5" />
                                <span>Add to Cart</span>
                            </button>
                        </section>
                    </template>
                </div>
            </div>

            <div class="space-y-4">
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">Payment &amp; Customer</h2>

                    <div class="space-y-4">
                        <div class="flex flex-col gap-2 text-[0.6rem] font-semibold text-gray-600 dark:text-gray-300">
                            <span>Payment Type</span>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="setPaymentType('paid')"
                                    :class="paymentType === 'paid' ? 'border-primary-600 bg-primary-600 text-white hover:bg-primary-500' : 'border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700'"
                                    class="flex items-center justify-center gap-1.5 rounded-md border px-3 py-2 text-[0.7rem] font-semibold transition focus:outline-none focus:ring-2 focus:ring-primary-500"
                                >
                                    <x-heroicon-o-banknotes class="h-4 w-4" />
                                    <span>Paid</span>
                                </button>
                                <button
                                    type="button"
                                    @click="setPaymentType('credit')"
                                    :class="paymentType === 'credit' ? 'border-primary-600 bg-primary-600 text-white hover:bg-primary-500' : 'border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700'"
                                    class="flex items-center justify-center gap-1.5 rounded-md border px-3 py-2 text-[0.7rem] font-semibold transition focus:outline-none focus:ring-2 focus:ring-primary-500"
                                >
                                    <x-heroicon-o-clipboard-document-list class="h-4 w-4" />
                                    <span>Credit</span>
                                </button>
                            </div>
                        </div>

                        <template x-if="showPaymentMethodSelect">
                            <label class="flex flex-col gap-1 text-[0.6rem] font-semibold text-gray-600 dark:text-gray-300">
                                <span>Payment Method</span>
                                <select
                                    x-model.number="paymentMethodId"
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                >
                                    <option value="">Select a method</option>
                                    <template x-for="method in paymentMethods" :key="method.id">
                                        <option :value="method.id" x-text="method.name"></option>
                                    </template>
                                </select>
                            </label>
                        </template>

                        <div
                            class="flex flex-col gap-1 text-[0.6rem] font-semibold text-gray-600 dark:text-gray-300"
                            x-on:click.away="showCustomerResults = false"
                        >
                            <span>Customer</span>
                            <div class="relative">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="search"
                                        x-model="customerSearchTerm"
                                        @input="handleCustomerInput()"
                                        @input.debounce.300ms="searchCustomers()"
                                        @focus="focusCustomerSearch()"
                                        @keydown.escape.stop.prevent="showCustomerResults = false"
                                        placeholder="Search name, email, or phone"
                                        autocomplete="off"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                    >

                                    <button
                                        type="button"
                                        x-show="customerId || (customerSearchTerm ?? '').length"
                                        @click="clearCustomerSelection()"
                                        class="inline-flex items-center rounded-md border border-gray-200 bg-gray-100 px-2 py-1 text-[0.55rem] font-semibold text-gray-600 transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
                                    >
                                        Clear
                                    </button>
                                </div>

                                <div
                                    x-cloak
                                    x-show="showCustomerResults"
                                    class="absolute z-30 mt-1 w-full overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-900"
                                >
                                    <template x-if="isSearchingCustomers">
                                        <p class="px-3 py-2 text-[0.6rem] text-gray-500 dark:text-gray-400">Searching…</p>
                                    </template>

                                    <template x-if="!isSearchingCustomers && customerSearchResults.length === 0 && (customerSearchTerm ?? '').trim().length >= 2">
                                        <p class="px-3 py-2 text-[0.6rem] text-gray-500 dark:text-gray-400">No customers found.</p>
                                    </template>

                                    <template x-for="customer in customerSearchResults" :key="customer.id">
                                        <button
                                            type="button"
                                            @click="selectCustomer(customer)"
                                            class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-[0.6rem] transition hover:bg-primary-50 focus:outline-none focus:bg-primary-100 dark:hover:bg-primary-500/20"
                                        >
                                            <span class="font-semibold text-gray-800 dark:text-gray-100" x-text="customer.name"></span>
                                            <span class="text-[0.55rem] text-gray-500 dark:text-gray-400" x-text="customer.email ?? customer.phone ?? ''"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <p class="text-[0.55rem] font-medium text-gray-500 dark:text-gray-400">
                                <span class="text-gray-900 dark:text-gray-100" x-text="customerName || 'Walk-in customer'"></span>
                                <template x-if="paymentType === 'credit' && !customerId">
                                    <span class="ml-1 text-red-500 dark:text-red-400">(required for credit)</span>
                                </template>
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">Cart</h2>

                    <div class="space-y-3">
                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10 text-xs">
                                <thead class="bg-gray-50 dark:bg-white/5">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-semibold">Item</th>
                                        <th class="px-3 py-2 font-semibold">Qty</th>
                                        <th class="px-3 py-2 font-semibold">Discount</th>
                                        <th class="px-3 py-2 text-right font-semibold">Line Total</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                    <template x-if="cart.length === 0">
                                        <tr>
                                            <td colspan="5" class="px-3 py-6 text-center text-xs text-gray-500 dark:text-gray-400">No items in the cart yet.</td>
                                        </tr>
                                    </template>

                                    <template x-for="item in cart" :key="item.product_id">
                                        <tr>
                                            <td class="px-3 py-2 align-top">
                                                <div class="font-semibold text-gray-900 dark:text-gray-100" x-text="item.name"></div>
                                                <div class="text-[0.6rem] text-gray-500 dark:text-gray-400" x-text="item.sku ?? 'N/A'"></div>
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <input
                                                    type="number"
                                                    min="1"
                                                    x-model.number="item.quantity"
                                                    @change="updateQuantity(item)"
                                                    class="w-16 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                                >
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    x-model.number="item.discount"
                                                    @change="updateDiscount(item)"
                                                    class="w-20 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                                >
                                            </td>
                                            <td class="px-3 py-2 align-top text-right font-semibold" x-text="formatMoney(item.line_total)"></td>
                                            <td class="px-3 py-2 align-top text-right">
                                                <button
                                                    type="button"
                                                    @click="removeFromCart(item.product_id)"
                                                    class="rounded-md p-1.5 text-red-500 transition hover:bg-red-50 focus:outline-none focus:ring-1 focus:ring-red-400 dark:text-red-400 dark:hover:bg-red-500/10"
                                                >
                                                    <span class="sr-only">Remove</span>
                                                    <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-3">
                            <label class="inline-flex items-center gap-2 text-[0.6rem] font-semibold text-gray-600 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    class="h-3.5 w-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-white/10"
                                    x-model="hasOrderDiscount"
                                    @change="toggleOrderDiscount(hasOrderDiscount)"
                                >
                                <span>Apply order discount</span>
                            </label>

                            <template x-if="hasOrderDiscount">
                                <label class="flex flex-col gap-1 text-[0.6rem] font-semibold text-gray-600 dark:text-gray-300">
                                    <span>Order Discount</span>
                                    <div class="relative flex items-center">
                                        <span class="pointer-events-none absolute left-2 text-[0.6rem] text-gray-500 dark:text-gray-400">रू.</span>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            x-model.number="orderDiscount"
                                            @change="clampOrderDiscount(); if (Number(orderDiscount ?? 0) <= 0) { toggleOrderDiscount(false); }"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 pl-9 text-xs font-medium shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                        >
                                    </div>
                                </label>
                            </template>

                            <label class="flex flex-col gap-1 text-[0.6rem] font-semibold text-gray-600 dark:text-gray-300">
                                <span>Amount Tendered</span>
                                <div class="relative flex items-center">
                                    <span class="pointer-events-none absolute left-2 text-[0.6rem] text-gray-500 dark:text-gray-400">रू.</span>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        x-model.number="amountTendered"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 pl-9 text-xs font-medium shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                    >
                                </div>
                                <span class="text-[0.55rem] font-medium text-gray-500 dark:text-gray-400">Enter the amount received from the customer.</span>
                            </label>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4 text-xs dark:bg-white/5">
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                                <span class="font-semibold" x-text="formatMoney(subtotal)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Tax</span>
                                <span class="font-semibold" x-text="formatMoney(taxAmount)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Discount</span>
                                <span class="font-semibold" x-text="formatMoney(lineDiscount + (hasOrderDiscount ? Number(orderDiscount ?? 0) : 0))"></span>
                            </div>
                            <div class="mt-3 flex justify-between border-t border-gray-200 pt-3 text-sm font-semibold dark:border-white/10">
                                <span>Total</span>
                                <span x-text="formatMoney(grandTotal)"></span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Tendered</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100" x-text="formatMoney(effectiveAmountPaid)"></span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Change</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100" x-text="formatMoney(changeDue)"></span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Balance Due</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100" x-text="formatMoney(amountDue)"></span>
                            </div>
                        </div>

                        <div class="space-y-2.5">
                            <button
                                type="button"
                                @click="checkout(false)"
                                :disabled="isProcessing || cart.length === 0 || !canCheckout"
                                class="flex w-full items-center justify-center gap-1.5 rounded-md bg-primary-600 px-3 py-2 text-[0.75rem] font-semibold text-white transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-primary-500 dark:hover:bg-primary-400"
                            >
                                <x-heroicon-o-check-circle class="h-3.5 w-3.5" />
                                <span x-text="isProcessing ? 'Processing…' : 'Checkout'"></span>
                            </button>

                            <button
                                type="button"
                                @click="checkout(true)"
                                :disabled="isProcessing || cart.length === 0 || !canCheckout"
                                class="flex w-full items-center justify-center gap-1.5 rounded-md bg-emerald-600 px-3 py-2 text-[0.75rem] font-semibold text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <x-heroicon-o-credit-card class="h-3.5 w-3.5" />
                                <span x-text="isProcessing ? 'Processing…' : 'Checkout & Print Invoice'"></span>
                            </button>

                            <button
                                type="button"
                                @click="clearCart()"
                                class="flex w-full items-center justify-center gap-1.5 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-[0.75rem] font-semibold text-red-600 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-400 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20"
                            >
                                <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                <span>Clear Cart</span>
                            </button>

                            <p class="text-center text-[0.55rem] font-medium text-gray-500 dark:text-gray-400">
                                Shortcut: Command + K / Ctrl + K
                            </p>

                            <template x-if="lastSaleId">
                                <a
                                    :href="invoiceUrl(lastSaleId)"
                                    target="_blank"
                                    class="flex w-full items-center justify-center gap-1.5 rounded-md border border-slate-500 bg-slate-600 px-3 py-2 text-[0.75rem] font-semibold text-white transition hover:bg-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-400"
                                >
                                    <x-heroicon-o-document-text class="h-3.5 w-3.5" />
                                    <span>Download Last Invoice</span>
                                </a>
                            </template>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">Recent Sales</h2>

                    <div class="space-y-2.5">
                        <template x-if="recentSales.length === 0">
                            <p class="text-xs text-gray-500 dark:text-gray-400">No sales recorded yet.</p>
                        </template>

                        <template x-for="sale in recentSales" :key="sale.id">
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-gray-100" x-text="sale.reference"></div>
                                    <div class="text-[0.55rem] text-gray-500 dark:text-gray-400" x-text="sale.sold_at_for_humans || 'Not set'"></div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold" x-text="formatMoney(sale.grand_total)"></span>
                                    <button
                                        type="button"
                                        @click="openInvoice(sale.id)"
                                        class="rounded-md border border-gray-200 bg-white p-1.5 text-gray-600 transition hover:border-primary-500 hover:text-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-primary-500/50 dark:hover:text-primary-400"
                                    >
                                        <span class="sr-only">Print invoice</span>
                                        <x-heroicon-o-printer class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </div>
        </div>

        <div
            x-cloak
            x-show="preview.open"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeProductPreview()"
        >
            <div
                x-transition
                class="relative w-full max-w-md overflow-hidden rounded-lg bg-white shadow-xl dark:bg-gray-900"
            >
                <button
                    type="button"
                    @click="closeProductPreview()"
                    class="absolute right-3 top-3 rounded-full p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-300 dark:hover:bg-gray-800"
                    aria-label="Close preview"
                >
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>

                <div class="space-y-3 p-5">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="preview.name"></h3>
                    <div class="overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800">
                        <template x-if="preview.imageUrl">
                            <img
                                :src="preview.imageUrl"
                                :alt="preview.name"
                                class="h-72 w-full object-contain"
                                loading="lazy"
                            >
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('posApp', (initial) => ({
                categories: initial.categories ?? [],
                products: initial.products ?? [],
                customers: initial.customers ?? [],
                paymentMethods: initial.paymentMethods ?? [],
                heldOrders: initial.heldOrders ?? [],
                recentSales: initial.recentSales ?? [],
                filters: {
                    search: '',
                    activeCategory: null,
                },
                scanner: {
                    input: '',
                },
                cart: [],
                customerId: initial.defaults?.customer_id !== undefined && initial.defaults?.customer_id !== null
                    ? Number(initial.defaults?.customer_id)
                    : null,
                customerName: initial.defaults?.customer_name ?? '',
                customerSearchTerm: initial.defaults?.customer_name ?? '',
                customerSearchResults: [],
                isSearchingCustomers: false,
                showCustomerResults: false,
                defaultPaymentMethodId: initial.defaults?.payment_method_id !== undefined && initial.defaults?.payment_method_id !== null
                    ? Number(initial.defaults?.payment_method_id)
                    : null,
                paymentMethodId: initial.defaults?.payment_method_id !== undefined && initial.defaults?.payment_method_id !== null
                    ? Number(initial.defaults?.payment_method_id)
                    : null,
                paymentType: initial.defaults?.payment_type ?? 'paid',
                orderDiscount: Number(initial.defaults?.order_discount ?? 0),
                hasOrderDiscount: Number(initial.defaults?.order_discount ?? 0) > 0,
                amountPaid: Number(initial.defaults?.amount_paid ?? 0),
                amountTendered: Number(initial.defaults?.amount_paid ?? 0),
                lastSaleId: initial.defaults?.last_sale_id ?? null,
                invoiceTemplate: initial.defaults?.invoice_url_template ?? '',
                isProcessing: false,
                holdName: '',
                shouldPrintInvoice: true,
                preview: {
                    open: false,
                    imageUrl: null,
                    name: '',
                },
                init() {
                    this.cart = [];
                    if (!this.defaultPaymentMethodId && this.paymentMethods.length) {
                        this.defaultPaymentMethodId = this.paymentMethods[0]?.id ?? null;
                    }

                    if (this.paymentType === 'paid' && !this.paymentMethodId) {
                        this.paymentMethodId = this.defaultPaymentMethodId;
                    }

                    this.customerSearchTerm = this.customerName ?? '';

                    if (!this.hasOrderDiscount) {
                        this.orderDiscount = 0;
                    }

                    this._shortcutHandler = (event) => {
                        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                            event.preventDefault();
                            this.clearCart();
                        }
                    };
                    window.addEventListener('keydown', this._shortcutHandler);

                    Livewire.on('pos:checkout-completed', (payload) => this.handleCheckoutCompleted(payload ?? {}));
                    Livewire.on('pos:hold-completed', (payload) => this.handleHoldCompleted(payload ?? {}));
                    Livewire.on('pos:held-order-deleted', (payload) => this.handleHeldOrderDeleted(payload ?? {}));
                    Livewire.on('pos:held-order-resumed', (payload) => this.handleHeldOrderResumed(payload ?? {}));
                    Livewire.on('pos:customers-found', (payload) => this.handleCustomersFound(payload ?? {}));
                },
                destroy() {
                    window.removeEventListener('keydown', this._shortcutHandler);
                },
                openProductPreview(product) {
                    if (!product || !product.image_url) {
                        return;
                    }

                    this.preview.name = product.name ?? 'Product preview';
                    this.preview.imageUrl = product.image_url;
                    this.preview.open = true;
                },
                closeProductPreview() {
                    this.preview.open = false;
                    this.preview.imageUrl = null;
                    this.preview.name = '';
                },
                setPaymentType(type) {
                    if (!['paid', 'credit'].includes(type)) {
                        return;
                    }

                    this.paymentType = type;

                    if (type === 'paid') {
                        this.paymentMethodId = this.defaultPaymentMethodId;
                    } else {
                        this.paymentMethodId = null;
                        this.amountPaid = 0;
                        this.amountTendered = 0;
                    }
                },
                get showPaymentMethodSelect() {
                    return this.paymentType === 'paid';
                },
                handleCustomerInput() {
                    this.customerId = null;
                    this.customerName = '';

                    const term = (this.customerSearchTerm ?? '').trim();

                    if (term.length === 0) {
                        this.customerSearchResults = [];
                        this.isSearchingCustomers = false;
                        this.showCustomerResults = false;
                        return;
                    }

                    this.showCustomerResults = true;
                },
                focusCustomerSearch() {
                    if (this.customerSearchResults.length > 0) {
                        this.showCustomerResults = true;
                    }
                },
                searchCustomers() {
                    const term = (this.customerSearchTerm ?? '').trim();

                    if (term.length < 2) {
                        this.customerSearchResults = [];
                        this.isSearchingCustomers = false;
                        this.showCustomerResults = false;
                        return;
                    }

                    if (this.customerName && term === this.customerName) {
                        this.customerSearchResults = [];
                        this.isSearchingCustomers = false;
                        this.showCustomerResults = false;
                        return;
                    }

                    this.isSearchingCustomers = true;
                    this.showCustomerResults = true;

                    Livewire.dispatch('pos:search-customers', {
                        payload: {
                            term,
                            limit: 8,
                        },
                    });
                },
                handleCustomersFound({ customers } = {}) {
                    this.isSearchingCustomers = false;
                    this.customerSearchResults = Array.isArray(customers) ? customers : [];
                    const termLength = (this.customerSearchTerm ?? '').trim().length;
                    this.showCustomerResults = termLength >= 2;
                },
                selectCustomer(customer) {
                    if (!customer) {
                        return;
                    }

                    this.customerId = customer.id ?? null;
                    this.customerName = customer.name ?? '';
                    this.customerSearchTerm = this.customerName ?? '';
                    this.customerSearchResults = [];
                    this.showCustomerResults = false;
                },
                clearCustomerSelection() {
                    this.customerId = null;
                    this.customerName = '';
                    this.customerSearchTerm = '';
                    this.customerSearchResults = [];
                    this.showCustomerResults = false;
                },
                toggleOrderDiscount(enabled) {
                    this.hasOrderDiscount = enabled;

                    if (!enabled) {
                        this.orderDiscount = 0;
                        this.clampOrderDiscount();
                    }
                },
                openInvoice(saleId) {
                    if (!saleId) {
                        return;
                    }

                    window.open(this.invoiceUrl(saleId), '_blank');
                },
                get filteredProducts() {
                    const term = (this.filters.search ?? '').trim().toLowerCase();
                    return this.products.filter((product) => {
                        const matchesCategory = this.filters.activeCategory === null
                            || product.product_category_id === this.filters.activeCategory;

                        if (!matchesCategory) {
                            return false;
                        }

                        if (term === '') {
                            return true;
                        }

                        return [product.name, product.sku, product.barcode]
                            .filter(Boolean)
                            .some((value) => value.toLowerCase().includes(term));
                    });
                },
                get subtotal() {
                    return this.cart.reduce((total, item) => total + (item.line_subtotal ?? 0), 0);
                },
                get lineDiscount() {
                    return this.cart.reduce((total, item) => total + Number(item.discount ?? 0), 0);
                },
                get subtotalAfterDiscount() {
                    return Math.max(this.subtotal - (this.lineDiscount + this.orderDiscount), 0);
                },
                get taxAmount() {
                    const lineNets = this.cart.map((item) => Math.max((item.line_subtotal ?? 0) - Number(item.discount ?? 0), 0));
                    const totalLineNet = lineNets.reduce((sum, value) => sum + value, 0);

                    if (totalLineNet <= 0) {
                        return 0;
                    }

                    const orderDiscount = this.orderDiscount;

                    return this.cart.reduce((total, item, index) => {
                        const lineNet = lineNets[index];
                        const taxRate = Number(item.tax_rate ?? 0);

                        if (lineNet <= 0 || taxRate <= 0) {
                            return total;
                        }

                        const share = lineNet / totalLineNet;
                        const taxableBase = Math.max(lineNet - (share * orderDiscount), 0);

                        return total + (taxableBase * taxRate / 100);
                    }, 0);
                },
                get grandTotal() {
                    return Math.max(this.subtotalAfterDiscount + this.taxAmount, 0);
                },
                get effectiveAmountPaid() {
                    return Math.max(Number(this.amountTendered ?? 0), Number(this.amountPaid ?? 0));
                },
                get amountDue() {
                    return Math.max(this.grandTotal - this.effectiveAmountPaid, 0);
                },
                get changeDue() {
                    return Math.max(this.effectiveAmountPaid - this.grandTotal, 0);
                },
                get canCheckout() {
                    if (this.cart.length === 0) {
                        return false;
                    }

                    if (this.paymentType === 'paid') {
                        return this.effectiveAmountPaid >= this.grandTotal;
                    }

                    if (this.paymentType === 'credit') {
                        return Boolean(this.customerId);
                    }

                    return this.effectiveAmountPaid >= this.grandTotal;
                },
                formatMoney(value) {
                    return 'रू. ' + new Intl.NumberFormat('ne-NP', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }).format(Number(value ?? 0));
                },
                invoiceUrl(id) {
                    return this.invoiceTemplate.replace('__SALE__', id);
                },
                addToCart(product) {
                    if (!product || product.stock_quantity <= 0) {
                        return;
                    }
                    //todo show message " No Stock Available " to add to cart when stock is 0 . check if product is of type service, no need to show quantity and check for availabilty



                    const existing = this.cart.find((item) => item.product_id === product.id);

                    if (existing) {
                        existing.quantity = Math.min(existing.quantity + 1, product.stock_quantity);
                        this.recalculateCartItem(existing);
                        return;
                    }

                    const item = this.createCartItem(product);
                    this.cart.push(item);
                    this.recalculateCartItem(item);
                    this.clampOrderDiscount();
                },
                updateQuantity(item) {
                    const product = this.productById(item.product_id);
                    if (!product) {
                        return;
                    }

                    item.quantity = Math.max(1, Math.min(Number(item.quantity ?? 1), product.stock_quantity));
                    this.recalculateCartItem(item);
                    this.clampOrderDiscount();
                },
                updateDiscount(item) {
                    item.discount = Math.max(0, Number(item.discount ?? 0));
                    this.recalculateCartItem(item);
                    this.clampOrderDiscount();
                },
                removeFromCart(productId) {
                    this.cart = this.cart.filter((item) => item.product_id !== productId);
                    this.clampOrderDiscount();
                },
                clearCart() {
                    this.cart = [];
                    this.orderDiscount = 0;
                    this.amountPaid = 0;
                    this.amountTendered = 0;
                    this.paymentType = 'paid';
                    this.customerId = null;
                    this.customerName = '';
                    this.customerSearchTerm = '';
                    this.customerSearchResults = [];
                    this.isSearchingCustomers = false;
                    this.showCustomerResults = false;
                    this.hasOrderDiscount = false;
                    this.paymentMethodId = this.defaultPaymentMethodId;
                    this.holdName = '';
                },
                applyBarcode() {
                    const code = (this.scanner.input ?? '').trim().toLowerCase();
                    if (code === '') {
                        return;
                    }

                    const product = this.products.find((item) => [item.barcode, item.sku]
                        .filter(Boolean)
                        .some((value) => value.toLowerCase() === code));

                    if (product) {
                        this.addToCart(product);
                    }

                    this.scanner.input = '';
                },
                checkout(shouldPrintInvoice = true) {
                    if (this.isProcessing || !this.canCheckout) {
                        return;
                    }

                    this.shouldPrintInvoice = shouldPrintInvoice;
                    this.isProcessing = true;
                    Livewire.dispatch('pos:checkout', { payload: this.buildPayload() });
                },
                holdCurrentOrder() {
                    if (this.cart.length === 0 || this.isProcessing) {
                        return;
                    }

                    this.isProcessing = true;
                    Livewire.dispatch('pos:hold-cart', { payload: this.buildPayload() });
                },
                resumeHeldOrder(heldOrderId) {
                    if (this.isProcessing) {
                        return;
                    }

                    this.isProcessing = true;
                    Livewire.dispatch('pos:resume-held-order', {
                        payload: { heldOrderId }
                    });
                },
                deleteHeldOrder(heldOrderId) {
                    if (this.isProcessing) {
                        return;
                    }

                    this.isProcessing = true;
                    Livewire.dispatch('pos:delete-held-order', {
                        payload: { heldOrderId }
                    });
                },
                handleCheckoutCompleted({ sale, recentSales, lastSaleId, products } = {}) {
                    this.isProcessing = false;

                    if (!sale) {
                        return;
                    }

                    this.recentSales = Array.isArray(recentSales) ? recentSales : this.recentSales;
                    this.products = Array.isArray(products) ? products : this.products;
                    this.clearCart();
                    this.lastSaleId = lastSaleId ?? sale.id ?? null;

                    const shouldPrint = this.shouldPrintInvoice;
                    this.shouldPrintInvoice = true;

                    if (sale.invoice_url && shouldPrint) {
                        window.open(sale.invoice_url, '_blank');
                    }
                },
                handleHoldCompleted({ heldOrder, heldOrders } = {}) {
                    this.isProcessing = false;
                    if (Array.isArray(heldOrders)) {
                        this.heldOrders = heldOrders;
                    } else if (heldOrder) {
                        this.heldOrders.unshift(heldOrder);
                    }
                    if (heldOrder) {
                        this.clearCart();
                    }
                },
                handleHeldOrderDeleted({ heldOrderId = null, heldOrders = null } = {}) {
                    this.isProcessing = false;

                    if (Array.isArray(heldOrders)) {
                        this.heldOrders = heldOrders;
                        return;
                    }

                    if (!heldOrderId) {
                        return;
                    }

                    this.heldOrders = this.heldOrders.filter((order) => order.id !== heldOrderId);
                },
                handleHeldOrderResumed({ cart, defaults, heldOrders } = {}) {
                    this.isProcessing = false;

                    if (Array.isArray(cart)) {
                        this.cart = cart.map((item) => this.enrichCartItem(item));
                    }

                    if (defaults) {
                        this.customerId = defaults.customer_id ?? null;
                        this.customerName = defaults.customer_name ?? '';
                        this.customerSearchTerm = this.customerName ?? '';
                        this.paymentType = defaults.payment_type ?? 'paid';
                        this.orderDiscount = Number(defaults.order_discount ?? 0);
                        this.hasOrderDiscount = this.orderDiscount > 0;
                        this.amountPaid = Number(defaults.amount_paid ?? 0);
                        this.amountTendered = this.amountPaid;
                        this.lastSaleId = defaults.last_sale_id ?? null;

                        if (this.paymentType === 'paid') {
                            this.paymentMethodId = defaults.payment_method_id ?? this.defaultPaymentMethodId;

                            if (!this.paymentMethodId) {
                                this.paymentMethodId = this.defaultPaymentMethodId;
                            }
                        } else {
                            this.paymentMethodId = null;
                        }

                        if (!this.hasOrderDiscount) {
                            this.orderDiscount = 0;
                        }

                        this.customerSearchResults = [];
                        this.showCustomerResults = false;
                    }

                    if (Array.isArray(heldOrders)) {
                        this.heldOrders = heldOrders;
                    }

                    this.clampOrderDiscount();
                },
                buildPayload() {
                    const paid = this.effectiveAmountPaid;
                    this.amountPaid = paid;

                    return {
                        customer_id: this.customerId,
                        customer_name: this.customerName,
                        payment_method_id: this.paymentType === 'paid' ? this.paymentMethodId : null,
                        payment_type: this.paymentType,
                        order_discount: this.hasOrderDiscount ? this.orderDiscount : 0,
                        amount_paid: paid,
                        hold_name: this.holdName,
                        cart: this.cart.map((item) => ({
                            product_id: item.product_id,
                            name: item.name,
                            sku: item.sku,
                            barcode: item.barcode,
                            unit_price: item.unit_price,
                            tax_rate: item.tax_rate,
                            quantity: item.quantity,
                            discount: item.discount,
                        })),
                    };
                },
                createCartItem(product) {
                    return {
                        product_id: product.id,
                        name: product.name,
                        sku: product.sku,
                        barcode: product.barcode,
                        unit_price: Number(product.unit_price ?? 0),
                        tax_rate: Number(product.tax_rate ?? 0),
                        quantity: 1,
                        discount: 0,
                        line_subtotal: 0,
                        line_net: 0,
                        line_total: 0,
                    };
                },
                enrichCartItem(item) {
                    const product = this.productById(item.product_id);
                    const quantity = Math.max(1, Number(item.quantity ?? 1));
                    return this.recalculateCartItem({
                        product_id: item.product_id,
                        name: item.name,
                        sku: item.sku,
                        barcode: item.barcode,
                        unit_price: Number(item.unit_price ?? product?.unit_price ?? 0),
                        tax_rate: Number(item.tax_rate ?? product?.tax_rate ?? 0),
                        quantity: product ? Math.min(quantity, product.stock_quantity) : quantity,
                        discount: Number(item.discount ?? 0),
                    });
                },
                recalculateCartItem(item) {
                    const product = this.productById(item.product_id);
                    if (product) {
                        item.quantity = Math.max(1, Math.min(Number(item.quantity ?? 1), product.stock_quantity));
                        item.unit_price = Number(product.unit_price ?? item.unit_price ?? 0);
                        item.tax_rate = Number(product.tax_rate ?? item.tax_rate ?? 0);
                    } else {
                        item.quantity = Math.max(1, Number(item.quantity ?? 1));
                        item.unit_price = Number(item.unit_price ?? 0);
                        item.tax_rate = Number(item.tax_rate ?? 0);
                    }

                    item.discount = Math.max(0, Number(item.discount ?? 0));

                    item.line_subtotal = Math.max(item.quantity * item.unit_price, 0);
                    item.line_net = Math.max(item.line_subtotal - item.discount, 0);
                    item.line_total = item.line_net;

                    return item;
                },
                clampOrderDiscount() {
                    const maxDiscount = Math.max(this.subtotal - this.lineDiscount, 0);
                    if (this.orderDiscount > maxDiscount) {
                        this.orderDiscount = Number(maxDiscount.toFixed(2));
                    }
                },
                productById(productId) {
                    return this.products.find((item) => item.id === productId) ?? null;
                },
            }));
        });
    </script>
@endpush
