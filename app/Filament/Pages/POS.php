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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;
use Filament\Pages\Page;

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

    public ?int $activeCategory = null;

    public string $search = '';

    public string $barcode = '';

    public float $orderDiscount = 0.0;

    public float $taxRate = 0.0;

    public float $amountPaid = 0.0;

    public string $holdName = '';

    public ?int $lastSaleId = null;

    public ?int $heldOrderId = null;

    protected $queryString = [
        'activeCategory' => ['except' => null],
    ];

    public function mount(): void
    {
        $this->cart = [];
    }

    public function updatedOrderDiscount($value): void
    {
        $this->orderDiscount = $this->sanitizeMoney($value);
        $this->clampOrderDiscount();
    }

    public function updatedTaxRate($value): void
    {
        $this->taxRate = max(0, round((float) $value, 2));
    }

    public function updatedAmountPaid($value): void
    {
        $this->amountPaid = $this->sanitizeMoney($value);
    }

    public function updatedCart($value, $key): void
    {
        if (! str_contains($key, '.')) {
            return;
        }

        [$rowKey, $field] = explode('.', $key);

        if (! isset($this->cart[$rowKey])) {
            return;
        }

        if ($field === 'quantity') {
            $quantity = max(1, (int) $value);
            $productId = (int) ($this->cart[$rowKey]['product_id'] ?? 0);
            $product = $productId ? ProductItem::find($productId) : null;

            if ($product instanceof ProductItem) {
                $available = max($product->stock_quantity, 0);
                $quantity = min($quantity, $available ?: $quantity);
                if ($available <= 0) {
                    Notification::make()
                        ->title('Out of stock')
                        ->body($product->name . ' is currently unavailable.')
                        ->warning()
                        ->send();
                    $quantity = 0;
                } elseif ($quantity < (int) $value) {
                    Notification::make()
                        ->title('Limited stock')
                        ->body('Only ' . $available . ' units of ' . $product->name . ' are available.')
                        ->warning()
                        ->send();
                }
            }

            if ($quantity <= 0) {
                unset($this->cart[$rowKey]);
                return;
            }

            $this->cart[$rowKey]['quantity'] = $quantity;
        }

        if ($field === 'discount') {
            $this->cart[$rowKey]['discount'] = $this->sanitizeMoney($value);
        }

        $this->recalculateLine($rowKey);
        $this->clampOrderDiscount();
    }

    public function addProduct(int $productId): void
    {
        $product = ProductItem::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->first();

        if (! $product instanceof ProductItem) {
            Notification::make()
                ->title('Product unavailable')
                ->body('The selected product could not be found.')
                ->danger()
                ->send();

            return;
        }

        if ($product->stock_quantity <= 0) {
            Notification::make()
                ->title('Out of stock')
                ->body($product->name . ' is currently unavailable.')
                ->warning()
                ->send();

            return;
        }

        $key = (string) $product->id;
        $existingQuantity = $this->cart[$key]['quantity'] ?? 0;
        $newQuantity = min($existingQuantity + 1, max($product->stock_quantity, 0));

        if ($newQuantity === $existingQuantity) {
            Notification::make()
                ->title('Stock limit reached')
                ->body('No additional units of ' . $product->name . ' are available.')
                ->warning()
                ->send();

            return;
        }

        $this->cart[$key] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'unit_price' => (float) $product->unit_price,
            'quantity' => $newQuantity,
            'discount' => $this->cart[$key]['discount'] ?? 0.0,
        ];

        $this->recalculateLine($key);
        $this->clampOrderDiscount();

        Notification::make()
            ->title('Added to cart')
            ->body($product->name . ' was added to the cart.')
            ->success()
            ->send();
    }

    public function removeItem(string $rowKey): void
    {
        unset($this->cart[$rowKey]);
        $this->clampOrderDiscount();
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->customerId = null;
        $this->paymentMethodId = null;
        $this->paymentType = 'paid';
        $this->orderDiscount = 0.0;
        $this->taxRate = 0.0;
        $this->amountPaid = 0.0;
        $this->holdName = '';
        $this->heldOrderId = null;
        $this->search = '';
        $this->barcode = '';
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->activeCategory = $categoryId;
    }

    public function scanBarcode(): void
    {
        $code = trim($this->barcode);

        if ($code === '') {
            return;
        }

        $product = ProductItem::query()
            ->where(fn (Builder $query) => $query
                ->where('barcode', $code)
                ->orWhere('sku', $code))
            ->where('is_active', true)
            ->first();

        $this->barcode = '';

        if (! $product instanceof ProductItem) {
            Notification::make()
                ->title('No match found')
                ->body('No product matches the scanned code.')
                ->warning()
                ->send();

            return;
        }

        $this->addProduct($product->id);
    }

    public function holdOrder(): void
    {
        if (empty($this->cart)) {
            Notification::make()
                ->title('Nothing to hold')
                ->body('Add at least one product before holding the order.')
                ->warning()
                ->send();

            return;
        }

        $label = trim($this->holdName) ?: 'Hold ' . now()->format('H:i');

        HeldOrder::create([
            'user_id' => Auth::id(),
            'customer_id' => $this->customerId,
            'payment_method_id' => $this->paymentMethodId,
            'label' => Str::limit($label, 80),
            'payment_type' => $this->paymentType,
            'order_discount' => $this->orderDiscount,
            'tax_rate' => $this->taxRate,
            'amount_paid' => $this->amountPaid,
            'cart' => [
                'items' => collect($this->cart)
                    ->map(fn (array $item) => [
                        'product_id' => $item['product_id'],
                        'name' => $item['name'],
                        'sku' => $item['sku'],
                        'barcode' => $item['barcode'],
                        'unit_price' => $item['unit_price'],
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
    }

    public function resumeOrder(int $orderId): void
    {
        $heldOrder = HeldOrder::query()->find($orderId);

        if (! $heldOrder instanceof HeldOrder) {
            Notification::make()
                ->title('Held order missing')
                ->body('The selected held order could not be found.')
                ->danger()
                ->send();

            return;
        }

        $items = Arr::get($heldOrder->cart, 'items', []);

        $this->cart = collect($items)
            ->mapWithKeys(function ($item) {
                $productId = (int) ($item['product_id'] ?? $item['id'] ?? 0);

                if ($productId <= 0) {
                    return [];
                }

                $rowKey = (string) $productId;

                return [
                    $rowKey => [
                        'product_id' => $productId,
                        'name' => $item['name'] ?? 'Product',
                        'sku' => $item['sku'] ?? null,
                        'barcode' => $item['barcode'] ?? null,
                        'unit_price' => (float) ($item['unit_price'] ?? 0),
                        'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                        'discount' => $this->sanitizeMoney($item['discount'] ?? 0),
                    ],
                ];
            })
            ->all();

        foreach (array_keys($this->cart) as $rowKey) {
            $productId = (int) ($this->cart[$rowKey]['product_id'] ?? 0);
            $product = $productId ? ProductItem::find($productId) : null;

            if ($product instanceof ProductItem) {
                $available = max($product->stock_quantity, 0);

                if ($available <= 0) {
                    unset($this->cart[$rowKey]);

                    Notification::make()
                        ->title('Out of stock')
                        ->body($product->name . ' is no longer available and was removed from the cart.')
                        ->warning()
                        ->send();

                    continue;
                }

                if ($this->cart[$rowKey]['quantity'] > $available) {
                    $this->cart[$rowKey]['quantity'] = $available;

                    Notification::make()
                        ->title('Stock adjusted')
                        ->body('Quantity for ' . $product->name . ' was reduced to available stock (' . $available . ').')
                        ->warning()
                        ->send();
                }
            }

            $this->recalculateLine($rowKey);
        }

        $this->customerId = $heldOrder->customer_id;
        $this->paymentMethodId = $heldOrder->payment_method_id;
        $this->paymentType = $heldOrder->payment_type;
        $this->orderDiscount = (float) $heldOrder->order_discount;
        $this->taxRate = (float) $heldOrder->tax_rate;
        $this->amountPaid = (float) $heldOrder->amount_paid;
        $this->holdName = $heldOrder->label;
        $this->heldOrderId = $heldOrder->id;
        $this->lastSaleId = null;

        $label = $heldOrder->label;
        $heldOrder->delete();

        $this->clampOrderDiscount();

        Notification::make()
            ->title('Order resumed')
            ->body('Held order "' . $label . '" loaded into the cart.')
            ->success()
            ->send();
    }

    public function checkout(): void
    {
        if (empty($this->cart)) {
            Notification::make()
                ->title('Cart empty')
                ->body('Add items to the cart before checking out.')
                ->warning()
                ->send();

            return;
        }

        $this->clampOrderDiscount();

        $subtotal = $this->subtotal;
        $lineDiscount = $this->lineDiscount;
        $totalDiscount = $lineDiscount + $this->orderDiscount;
        $taxAmount = $this->taxAmount;
        $grandTotal = max($subtotal - $totalDiscount + $taxAmount, 0);
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

            return;
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
            ->select(['id', 'name', 'sku', 'barcode', 'unit_price', 'stock_quantity', 'product_category_id'])
            ->where('is_active', true)
            ->when($this->activeCategory, fn ($query) => $query->where('product_category_id', $this->activeCategory))
            ->when($this->search !== '', function ($query) {
                $search = $this->search;

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('sku', 'like', '%' . $search . '%')
                        ->orWhere('barcode', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name')
            ->limit(30)
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
            ->sum(fn ($item) => ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0)), 2);
    }

    public function getLineDiscountProperty(): float
    {
        return round(collect($this->cart)->sum(fn ($item) => $item['discount'] ?? 0), 2);
    }

    public function getTaxableAmountProperty(): float
    {
        return max($this->subtotal - ($this->lineDiscount + $this->orderDiscount), 0);
    }

    public function getTaxAmountProperty(): float
    {
        return round($this->taxableAmount * ($this->taxRate / 100), 2);
    }

    public function getGrandTotalProperty(): float
    {
        return round(max($this->taxableAmount + $this->taxAmount, 0), 2);
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

        $lineTotal = ($item['quantity'] * $item['unit_price']) - $item['discount'];
        $item['line_total'] = round(max($lineTotal, 0), 2);
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
