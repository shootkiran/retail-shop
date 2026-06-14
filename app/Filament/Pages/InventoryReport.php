<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Resources\ProductItems\ProductItemResource;
use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\Vendor;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class InventoryReport extends Page
{
    use RequiresBackOffice;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-archive-box';

    protected static UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'Inventory Report';

    protected string $view = 'filament.pages.inventory-report';

    public ?int $categoryId = null;

    public ?int $vendorId = null;

    public string $stockStatus = 'all';

    public function mount(): void
    {
        $this->stockStatus = 'all';
    }

    /**
     * @return Collection<int, array{
     *     id:int,
     *     name:string,
     *     product_url:?string,
     *     category:?string,
     *     vendor:?string,
     *     display_stock:string,
     *     base_quantity:string,
     *     reorder_level:int,
     *     status:string,
     *     status_label:string,
     *     unit_cost:string,
     *     stock_value:string,
     *     base_quantity_raw:float,
     *     stock_value_raw:float
     * }>
     */
    public function getInventoryRowsProperty(): Collection
    {
        return $this->inventoryQuery()
            ->orderBy('name')
            ->get()
            ->map(function (ProductItem $item): array {
                $baseQuantity = (float) $item->stock_quantity;
                $reorderLevel = (int) $item->reorder_level;
                $unit = $item->baseUnit;
                $multiplier = max((float) ($unit?->multiplier_to_base ?? 1), 1.0);
                $displayQuantity = $baseQuantity / $multiplier;
                $unitLabel = $unit?->symbol ?: $unit?->name ?: 'pcs';
                $status = $this->resolveStatus($baseQuantity, $reorderLevel);
                $stockValue = $baseQuantity * (float) $item->unit_cost;

                return [
                    'id' => $item->getKey(),
                    'name' => $item->name,
                    'product_url' => ProductItemResource::getUrl('view', ['record' => $item]),
                    'category' => $item->category?->name,
                    'vendor' => $item->vendor?->name,
                    'display_stock' => number_format($displayQuantity, 2) . ' ' . $unitLabel,
                    'base_quantity' => number_format($baseQuantity, 4),
                    'reorder_level' => $reorderLevel,
                    'status' => $status,
                    'status_label' => str_replace('_', ' ', ucfirst($status)),
                    'unit_cost' => $this->formatMoney((float) $item->unit_cost),
                    'stock_value' => $this->formatMoney($stockValue),
                    'base_quantity_raw' => $baseQuantity,
                    'stock_value_raw' => $stockValue,
                ];
            });
    }

    /**
     * @return array{total_skus:int, items_in_stock:int, low_stock_items:int, total_valuation:float}
     */
    public function getSummaryProperty(): array
    {
        $rows = $this->inventoryRows;

        return [
            'total_skus' => $rows->count(),
            'items_in_stock' => $rows->filter(fn (array $row): bool => (float) $row['base_quantity_raw'] > 0)->count(),
            'low_stock_items' => $rows->filter(fn (array $row): bool => $row['status'] === 'low_stock')->count(),
            'total_valuation' => $rows->sum(fn (array $row): float => (float) $row['stock_value_raw']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getStockStatusOptionsProperty(): array
    {
        return [
            'all' => 'All stock',
            'in_stock' => 'In stock',
            'low_stock' => 'Low stock',
            'out_of_stock' => 'Out of stock',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getCategoryOptionsProperty(): array
    {
        return ProductCategory::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function getVendorOptionsProperty(): array
    {
        return Vendor::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function inventoryQuery(): Builder
    {
        return ProductItem::query()
            ->with([
                'category:id,name',
                'vendor:id,name',
                'baseUnit:id,name,symbol,multiplier_to_base',
            ])
            ->when($this->categoryId, fn (Builder $query, int $categoryId): Builder => $query->where('product_category_id', $categoryId))
            ->when($this->vendorId, fn (Builder $query, int $vendorId): Builder => $query->where('vendor_id', $vendorId))
            ->when($this->stockStatus !== 'all', function (Builder $query): Builder {
                return match ($this->stockStatus) {
                    'in_stock' => $query->whereRaw('stock_quantity > COALESCE(reorder_level, 0)')->where('stock_quantity', '>', 0),
                    'low_stock' => $query->whereRaw('stock_quantity > 0 and stock_quantity <= COALESCE(reorder_level, 0)'),
                    'out_of_stock' => $query->where('stock_quantity', '<=', 0),
                    default => $query,
                };
            });
    }

    protected function resolveStatus(float $stockQuantity, int $reorderLevel): string
    {
        if ($stockQuantity <= 0) {
            return 'out_of_stock';
        }

        if ($stockQuantity <= $reorderLevel) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return config('retail.currency.symbol', 'रू') . ' ' . number_format((float) $amount, 2);
    }

    public function statusClasses(string $status): string
    {
        return match ($status) {
            'in_stock' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300',
            'low_stock' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
            'out_of_stock' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-500/10 dark:text-gray-300',
        };
    }
}
