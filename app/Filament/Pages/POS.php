<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\HeldOrder;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\Sale;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;
use Filament\Pages\Page;
use Livewire\Attributes\On;

class POS extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static UnitEnum|string|null $navigationGroup = 'Point of Sale';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Point of Sale';

    protected string $view = 'filament.pages.pos';

    public ?int $customerId = null;

    public ?int $paymentMethodId = null;

    public string $paymentType = 'paid';

    public array $cart = [];

    public $activeCategory = null;

    public string $search = '';

    public string $barcode = '';

    public float $orderDiscount = 0.0;

    public float $amountPaid = 0.0;

    public string $holdName = '';

    public ?int $lastSaleId = null;

    public ?int $heldOrderId = null;

    public function mount(): void
    {
        $this->cart = [];
        $this->paymentMethodId = $this->resolveDefaultPaymentMethodId();
    }

    #[On('pos:checkout')]
    public function checkoutFromClient(array $payload = []): void
    {
        $payload = $this->normalizeEventPayload($payload);
        $this->hydrateComponentState($payload);

        $sale = $this->checkout();

        if (! $sale instanceof Sale) {
            $this->dispatch(
                'pos:checkout-completed',
                sale: null,
                recentSales: $this->recentSalesPayload(),
                lastSaleId: $this->lastSaleId,
                products: $this->productsPayload(),
            );

            return;
        }

        $this->dispatch(
            'pos:checkout-completed',
            sale: $this->formatSalePayload($sale),
            recentSales: $this->recentSalesPayload(),
            lastSaleId: $this->lastSaleId,
            products: $this->productsPayload(),
        );
    }

    #[On('pos:hold-cart')]
    public function holdOrderFromClient(array $payload = []): void
    {
        $payload = $this->normalizeEventPayload($payload);
        $this->hydrateComponentState($payload);

        $heldOrder = $this->holdOrder();

        if (! $heldOrder instanceof HeldOrder) {
            $this->dispatch(
                'pos:hold-completed',
                heldOrder: null,
                heldOrders: $this->heldOrdersPayload(),
            );

            return;
        }

        $this->dispatch(
            'pos:hold-completed',
            heldOrder: $this->formatHeldOrderPayload($heldOrder),
            heldOrders: $this->heldOrdersPayload(),
        );
    }

    #[On('pos:delete-held-order')]
    public function deleteHeldOrder(array $payload = []): void
    {
        $payload = $this->normalizeEventPayload($payload);
        $heldOrderId = isset($payload['heldOrderId']) ? (int) $payload['heldOrderId'] : null;

        if (! $heldOrderId) {
            return;
        }

        $heldOrder = HeldOrder::query()->find($heldOrderId);

        if (! $heldOrder instanceof HeldOrder) {
            return;
        }

        $heldOrder->delete();

        $this->dispatch('pos:held-order-deleted', heldOrderId: $heldOrderId);
    }

    public function getFrontendStateProperty(): array
    {
        return [
            'categories' => $this->categoriesPayload(),
            'products' => $this->productsPayload(),
            'customers' => $this->customersPayload(),
            'paymentMethods' => $this->paymentMethodsPayload(),
            'heldOrders' => $this->heldOrdersPayload(),
            'recentSales' => $this->recentSalesPayload(),
            'defaults' => $this->defaultsPayload(),
        ];
    }

    public function getInvoiceUrlTemplateProperty(): string
    {
        return route('sales.invoice', ['sale' => '__SALE__']);
    }

    protected function hydrateComponentState(array $payload = []): void
    {
        $payload = $this->normalizeEventPayload($payload);

        $this->customerId = $this->nullableInt($payload['customer_id'] ?? null);
        $this->paymentMethodId = $this->nullableInt($payload['payment_method_id'] ?? $this->resolveDefaultPaymentMethodId());
        $this->paymentType = in_array($payload['payment_type'] ?? null, ['paid', 'credit'], true)
            ? $payload['payment_type']
            : 'paid';
        $this->orderDiscount = $this->sanitizeMoney($payload['order_discount'] ?? 0);
        $this->amountPaid = $this->sanitizeMoney($payload['amount_paid'] ?? 0);
        $this->holdName = trim((string) ($payload['hold_name'] ?? ''));

        $this->hydrateCartFromPayload($payload['cart'] ?? []);
    }

    protected function hydrateCartFromPayload(array $items): void
    {
        $this->cart = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $product = ProductItem::query()
                ->select(['id', 'name', 'sku', 'barcode', 'unit_price', 'tax_rate', 'stock_quantity'])
                ->find($productId);

            if (! $product instanceof ProductItem) {
                continue;
            }

            $rowKey = (string) $productId;
            $availableStock = max((int) $product->stock_quantity, 0);
            $requestedQuantity = max(1, (int) ($item['quantity'] ?? 1));
            $quantity = $availableStock > 0 ? min($requestedQuantity, $availableStock) : $requestedQuantity;

            $this->cart[$rowKey] = [
                'product_id' => $productId,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'unit_price' => round((float) $product->unit_price, 2),
                'tax_rate' => round(max((float) $product->tax_rate, 0), 2),
                'quantity' => $quantity,
                'discount' => $this->sanitizeMoney($item['discount'] ?? 0),
            ];

            $this->recalculateLine($rowKey);
        }

        $this->clampOrderDiscount();
    }

    protected function formatHeldOrderPayload(HeldOrder $heldOrder): array
    {
        $items = collect(data_get($heldOrder->cart, 'items', []))
            ->map(fn ($item) => [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'name' => $item['name'] ?? 'Product',
                'sku' => $item['sku'] ?? null,
                'barcode' => $item['barcode'] ?? null,
                'unit_price' => round((float) ($item['unit_price'] ?? 0), 2),
                'tax_rate' => round(max((float) ($item['tax_rate'] ?? 0), 0), 2),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'discount' => $this->sanitizeMoney($item['discount'] ?? 0),
            ])
            ->filter(fn ($item) => $item['product_id'] > 0)
            ->values()
            ->all();

        return [
            'id' => $heldOrder->id,
            'label' => $heldOrder->label ?? 'Untitled order',
            'customer_id' => $heldOrder->customer_id,
            'customer_name' => $heldOrder->customer?->name ?? 'Walk-in customer',
            'payment_method_id' => $heldOrder->payment_method_id,
            'payment_type' => $heldOrder->payment_type,
            'order_discount' => (float) $heldOrder->order_discount,
            'amount_paid' => (float) $heldOrder->amount_paid,
            'cart' => $items,
            'preview' => $this->buildHeldOrderPreview($items),
            'updated_at_for_humans' => optional($heldOrder->updated_at)->diffForHumans() ?? '',
        ];
    }

    protected function formatSalePayload(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'reference' => $sale->reference,
            'grand_total' => (float) $sale->grand_total,
            'sold_at_for_humans' => optional($sale->sold_at)->diffForHumans() ?? now()->diffForHumans(),
            'invoice_url' => str_replace('__SALE__', (string) $sale->id, $this->invoiceUrlTemplate),
        ];
    }

    protected function categoriesPayload(): array
    {
        return $this->categories
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();
    }

    protected function productsPayload(): array
    {
        return $this->products
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'unit_price' => (float) $product->unit_price,
                'tax_rate' => (float) $product->tax_rate,
                'stock_quantity' => (int) $product->stock_quantity,
                'product_category_id' => $product->product_category_id,
            ])
            ->values()
            ->all();
    }

    protected function customersPayload(): array
    {
        return $this->customers
            ->map(fn ($customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
            ])
            ->values()
            ->all();
    }

    protected function paymentMethodsPayload(): array
    {
        return $this->paymentMethods
            ->map(fn ($method) => [
                'id' => $method->id,
                'name' => $method->name,
            ])
            ->values()
            ->all();
    }

    protected function heldOrdersPayload(): array
    {
        return $this->heldOrders
            ->map(fn ($heldOrder) => $this->formatHeldOrderPayload($heldOrder))
            ->all();
    }

    protected function recentSalesPayload(): array
    {
        return $this->recentSales
            ->map(fn ($sale) => [
                'id' => $sale->id,
                'reference' => $sale->reference,
                'grand_total' => (float) $sale->grand_total,
                'sold_at_for_humans' => optional($sale->sold_at)->diffForHumans() ?? '',
            ])
            ->all();
    }

    protected function buildHeldOrderPreview(array $items): string
    {
        if (empty($items)) {
            return 'No items';
        }

        $previewItems = collect($items)
            ->take(3)
            ->map(fn ($item) => $item['quantity'] . ' x ' . $item['name'])
            ->implode(', ');

        $remaining = max(count($items) - 3, 0);

        if ($remaining > 0) {
            $previewItems .= ' +' . $remaining . ' more';
        }

        return $previewItems;
    }

    protected function normalizeEventPayload($payload): array
    {
        if (is_array($payload)) {
            if (array_key_exists('payload', $payload) && is_array($payload['payload'])) {
                return $payload['payload'];
            }

            return $payload;
        }

        return [];
    }

    protected function nullableInt($value): ?int
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return (int) $value;
    }

    #[On('pos:resume-held-order')]
    public function resumeHeldOrderFromClient(array $payload = []): void
    {
        $payload = $this->normalizeEventPayload($payload);
        $heldOrderId = isset($payload['heldOrderId']) ? (int) $payload['heldOrderId'] : null;

        if (! $heldOrderId) {
            return;
        }

        $heldOrder = HeldOrder::query()
            ->with('customer:id,name')
            ->find($heldOrderId);

        if (! $heldOrder instanceof HeldOrder) {
            return;
        }

        $this->customerId = $heldOrder->customer_id;
        $this->paymentMethodId = $heldOrder->payment_method_id ?: $this->resolveDefaultPaymentMethodId();
        $this->paymentType = $heldOrder->payment_type;
        $this->orderDiscount = (float) $heldOrder->order_discount;
        $this->amountPaid = (float) $heldOrder->amount_paid;
        $this->lastSaleId = null;

        $this->hydrateCartFromPayload(data_get($heldOrder->cart, 'items', []));

        $label = $heldOrder->label ?? 'Held order';
        $heldOrder->delete();

        Notification::make()
            ->title('Order resumed')
            ->body('Held order "' . $label . '" loaded into the cart.')
            ->success()
            ->send();

        $this->dispatch(
            'pos:held-order-resumed',
            cart: $this->cartPayload(),
            defaults: $this->defaultsPayload(),
            heldOrders: $this->heldOrdersPayload(),
        );
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->customerId = null;
        $this->paymentMethodId = $this->resolveDefaultPaymentMethodId();
        $this->paymentType = 'paid';
        $this->orderDiscount = 0.0;
        $this->amountPaid = 0.0;
        $this->holdName = '';
        $this->heldOrderId = null;
    }

    public function holdOrder(): ?HeldOrder
    {
        if (empty($this->cart)) {
            Notification::make()
                ->title('Nothing to hold')
                ->body('Add at least one product before holding the order.')
                ->warning()
                ->send();

            return null;
        }

        $label = trim($this->holdName) ?: 'Hold ' . now()->format('H:i');

        $heldOrder = HeldOrder::create([
            'user_id' => Auth::id(),
            'customer_id' => $this->customerId,
            'payment_method_id' => $this->paymentMethodId,
            'label' => Str::limit($label, 80),
            'payment_type' => $this->paymentType,
            'order_discount' => $this->orderDiscount,
            'amount_paid' => $this->amountPaid,
            'cart' => [
                'items' => collect($this->cart)
                    ->map(fn (array $item) => [
                        'product_id' => $item['product_id'],
                        'name' => $item['name'],
                        'sku' => $item['sku'],
                        'barcode' => $item['barcode'],
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'] ?? 0,
                        'quantity' => $item['quantity'],
                        'discount' => $item['discount'] ?? 0,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);

        $this->clearCart();
        $this->lastSaleId = null;

        Notification::make()
            ->title('Order held')
            ->body('The order was saved as "' . $label . '" and can be resumed later.')
            ->success()
            ->send();

        return $heldOrder->refresh()->loadMissing('customer:id,name');
    }

    public function checkout(): ?Sale
    {
        if (empty($this->cart)) {
            Notification::make()
                ->title('Cart empty')
                ->body('Add items to the cart before checking out.')
                ->warning()
                ->send();

            return null;
        }

        $this->clampOrderDiscount();

        $subtotal = $this->subtotal;
        $lineDiscount = $this->lineDiscount;
        $totalDiscount = $lineDiscount + $this->orderDiscount;
        $subtotalAfterDiscount = $this->subtotalAfterDiscount;
        $taxAmount = $this->taxAmount;
        $grandTotal = max($subtotalAfterDiscount + $taxAmount, 0);
        $amountPaid = min($this->amountPaid, $grandTotal);
        $amountDue = max($grandTotal - $amountPaid, 0);
        $paymentStatus = $this->resolvePaymentStatus($amountPaid, $amountDue);

        $cartItems = collect($this->cart);
        $sale = null;

        try {
            DB::transaction(function () use (&$sale, $subtotal, $totalDiscount, $taxAmount, $grandTotal, $amountPaid, $amountDue, $paymentStatus, $cartItems): void {
                /** @var Sale $sale */
                $sale = Sale::create([
                    'customer_id' => $this->customerId,
                    'payment_method_id' => $this->paymentMethodId,
                    'status' => 'completed',
                    'payment_status' => $paymentStatus,
                    'payment_type' => $this->paymentType,
                    'total_amount' => $subtotal,
                    'discount_amount' => $totalDiscount,
                    'order_discount' => $this->orderDiscount,
                    'tax_amount' => $taxAmount,
                    'grand_total' => $grandTotal,
                    'amount_paid' => $amountPaid,
                    'amount_due' => $amountDue,
                    'sold_at' => now(),
                ]);

                $sale->items()->createMany(
                    $cartItems->map(fn (array $item) => [
                        'product_item_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'discount_amount' => $item['discount'] ?? 0,
                        'total_amount' => $item['line_net'] ?? max(($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0) - ($item['discount'] ?? 0), 0),
                    ])->all()
                );

                foreach ($cartItems as $item) {
                    ProductItem::query()
                        ->whereKey($item['product_id'])
                        ->decrement('stock_quantity', $item['quantity']);
                }
            });
        } catch (ModelNotFoundException|
            \Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Checkout failed')
                ->body('An unexpected error occurred while processing the sale.')
                ->danger()
                ->send();

            return null;
        }

        $saleId = $sale->id;
        $saleReference = $sale->reference;

        $this->clearCart();
        $this->lastSaleId = $saleId;

        Notification::make()
            ->title('Sale completed')
            ->body('Sale ' . $saleReference . ' has been recorded successfully.')
            ->success()
            ->send();

        return $sale;
    }

    protected function cartPayload(): array
    {
        return collect($this->cart)
            ->values()
            ->map(fn (array $item) => [
                'product_id' => $item['product_id'],
                'name' => $item['name'] ?? 'Product',
                'sku' => $item['sku'] ?? null,
                'barcode' => $item['barcode'] ?? null,
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
                'discount' => (float) ($item['discount'] ?? 0),
            ])
            ->all();
    }

    protected function defaultsPayload(): array
    {
        return [
            'customer_id' => $this->customerId,
            'payment_method_id' => $this->paymentMethodId,
            'payment_type' => $this->paymentType,
            'order_discount' => $this->orderDiscount,
            'amount_paid' => $this->amountPaid,
            'last_sale_id' => $this->lastSaleId,
            'invoice_url_template' => $this->invoiceUrlTemplate,
        ];
    }

    public function getCategoriesProperty(): Collection
    {
        return ProductCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getProductsProperty(): Collection
    {
        return ProductItem::query()
            ->select(['id', 'name', 'sku', 'barcode', 'unit_price', 'tax_rate', 'stock_quantity', 'product_category_id'])
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(200)
            ->get();
    }

    public function getCustomersProperty(): Collection
    {
        return Customer::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getPaymentMethodsProperty(): Collection
    {
        return PaymentMethod::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getHeldOrdersProperty(): Collection
    {
        return HeldOrder::query()
            ->with('customer:id,name')
            ->latest()
            ->limit(10)
            ->get(['id', 'label', 'customer_id', 'cart', 'updated_at']);
    }

    public function getRecentSalesProperty(): Collection
    {
        return Sale::query()
            ->latest('sold_at')
            ->latest()
            ->limit(5)
            ->get(['id', 'reference', 'grand_total', 'sold_at']);
    }

    public function getSubtotalProperty(): float
    {
        return round(collect($this->cart)
            ->sum(fn ($item) => $item['line_subtotal'] ?? (($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0))), 2);
    }

    public function getLineDiscountProperty(): float
    {
        return round(collect($this->cart)->sum(fn ($item) => $item['discount'] ?? 0), 2);
    }

    public function getSubtotalAfterDiscountProperty(): float
    {
        return round(max($this->subtotal - ($this->lineDiscount + $this->orderDiscount), 0), 2);
    }

    protected function resolveDefaultPaymentMethodId(): ?int
    {
        static $defaultPaymentMethodId;

        if ($defaultPaymentMethodId !== null) {
            return $defaultPaymentMethodId;
        }

        $defaultPaymentMethodId = PaymentMethod::query()
            ->where('is_active', true)
            ->where('name', 'Cash')
            ->value('id');

        if ($defaultPaymentMethodId === null) {
            $defaultPaymentMethodId = PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->value('id');
        }

        return $defaultPaymentMethodId;
    }

    public function getTaxAmountProperty(): float
    {
        $items = collect($this->cart);

        if ($items->isEmpty()) {
            return 0.0;
        }

        $lineTotals = $items->sum(function ($item) {
            $quantity = max((int) ($item['quantity'] ?? 0), 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);

            $lineSubtotal = max($quantity * $unitPrice, 0);
            return max($lineSubtotal - $discount, 0);
        });

        if ($lineTotals <= 0) {
            return 0.0;
        }

        $orderDiscount = $this->orderDiscount;

        $totalTax = $items->sum(function ($item) use ($lineTotals, $orderDiscount) {
            $quantity = max((int) ($item['quantity'] ?? 0), 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? 0);

            $lineSubtotal = max($quantity * $unitPrice, 0);
            $lineNet = max($lineSubtotal - $discount, 0);

            if ($lineNet <= 0 || $taxRate <= 0) {
                return 0.0;
            }

            $share = $lineNet / $lineTotals;
            $orderDiscountShare = $share * $orderDiscount;
            $taxableBase = max($lineNet - $orderDiscountShare, 0);

            return round($taxableBase * $taxRate / 100, 2);
        });

        return round($totalTax, 2);
    }

    public function getGrandTotalProperty(): float
    {
        return round(max($this->subtotalAfterDiscount + $this->taxAmount, 0), 2);
    }

    public function getAmountDueProperty(): float
    {
        return round(max($this->grandTotal - $this->amountPaid, 0), 2);
    }

    protected function recalculateLine(string $rowKey): void
    {
        if (! isset($this->cart[$rowKey])) {
            return;
        }

        $item = & $this->cart[$rowKey];

        $item['quantity'] = max(1, (int) ($item['quantity'] ?? 1));
        $item['unit_price'] = round((float) ($item['unit_price'] ?? 0), 2);
        $item['discount'] = $this->sanitizeMoney($item['discount'] ?? 0);
        $item['tax_rate'] = round(max((float) ($item['tax_rate'] ?? 0), 0), 2);

        $lineSubtotal = max($item['quantity'] * $item['unit_price'], 0);
        $lineNet = max($lineSubtotal - $item['discount'], 0);

        $item['line_subtotal'] = round($lineSubtotal, 2);
        $item['line_net'] = round($lineNet, 2);
        $item['line_total'] = $item['line_net'];
    }

    protected function clampOrderDiscount(): void
    {
        $maxDiscount = max($this->subtotal - $this->lineDiscount, 0);
        if ($this->orderDiscount > $maxDiscount) {
            $this->orderDiscount = round($maxDiscount, 2);
        }
    }

    protected function sanitizeMoney($value): float
    {
        return round(max((float) $value, 0), 2);
    }

    protected function resolvePaymentStatus(float $amountPaid, float $amountDue): string
    {
        if ($amountDue <= 0.01) {
            return 'paid';
        }

        if ($amountPaid > 0) {
            return 'partial';
        }

        return 'pending';
    }
}
