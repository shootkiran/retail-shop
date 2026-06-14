<?php

namespace App\Services;

use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InventoryCountSheetService
{
    /**
     * @param  array{
     *     search?: string|null,
     *     category_id?: int|string|null,
     *     vendor_id?: int|string|null,
     *     stock_status?: string|null
     * }  $filters
     * @return Builder<ProductItem>
     */
    public function query(array $filters = []): Builder
    {
        $query = ProductItem::query()
            ->with([
                'category:id,name',
                'vendor:id,name',
                'baseUnit:id,name,symbol,multiplier_to_base',
            ]);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('product_category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['vendor_id'])) {
            $query->where('vendor_id', (int) $filters['vendor_id']);
        }

        if (($filters['stock_status'] ?? 'all') !== 'all') {
            $query->when(($filters['stock_status'] ?? 'all') === 'in_stock', fn (Builder $builder): Builder => $builder->whereRaw('stock_quantity > COALESCE(reorder_level, 0)')->where('stock_quantity', '>', 0));
            $query->when(($filters['stock_status'] ?? 'all') === 'low_stock', fn (Builder $builder): Builder => $builder->whereRaw('stock_quantity > 0 and stock_quantity <= COALESCE(reorder_level, 0)'));
            $query->when(($filters['stock_status'] ?? 'all') === 'out_of_stock', fn (Builder $builder): Builder => $builder->where('stock_quantity', '<=', 0));
        }

        return $query;
    }

    /**
     * @param  array{
     *     search?: string|null,
     *     category_id?: int|string|null,
     *     vendor_id?: int|string|null,
     *     stock_status?: string|null
     * }  $filters
     * @return Collection<int, array{
     *     product_id:int,
     *     name:string,
     *     sku:?string,
     *     category:?string,
     *     vendor:?string,
     *     unit_id:?int,
     *     unit_name:string,
     *     unit_symbol:string,
     *     unit_multiplier:float,
     *     system_quantity_base:float,
     *     system_quantity_display:float,
     *     reorder_level:int,
     *     status:string,
     *     status_label:string,
     *     unit_cost:float,
     *     stock_value:float,
     *     display_stock:string
     * }>
     */
    public function rows(array $filters = []): Collection
    {
        return $this->query($filters)
            ->orderBy('name')
            ->get()
            ->map(function (ProductItem $item): array {
                $unit = $item->baseUnit;
                $multiplier = max((float) ($unit?->multiplier_to_base ?? 1), 1.0);
                $systemQuantityBase = (float) $item->stock_quantity;
                $systemQuantityDisplay = $unit ? $unit->fromBase($systemQuantityBase) : $systemQuantityBase;
                $status = $this->resolveStatus($systemQuantityBase, (int) $item->reorder_level);

                return [
                    'product_id' => $item->getKey(),
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'category' => $item->category?->name,
                    'vendor' => $item->vendor?->name,
                    'unit_id' => $unit?->getKey(),
                    'unit_name' => $unit?->name ?: 'Piece',
                    'unit_symbol' => $unit?->symbol ?: 'pcs',
                    'unit_multiplier' => $multiplier,
                    'system_quantity_base' => round($systemQuantityBase, 4),
                    'system_quantity_display' => round($systemQuantityDisplay, 4),
                    'reorder_level' => (int) $item->reorder_level,
                    'status' => $status,
                    'status_label' => str_replace('_', ' ', ucfirst($status)),
                    'unit_cost' => (float) $item->unit_cost,
                    'stock_value' => round($systemQuantityBase * (float) $item->unit_cost, 2),
                    'display_stock' => number_format($systemQuantityDisplay, 2).' '.($unit?->symbol ?: $unit?->name ?: 'pcs'),
                ];
            });
    }

    /**
     * @param  Collection<int, array{
     *     product_id:int,
     *     name:string,
     *     sku:?string,
     *     category:?string,
     *     vendor:?string,
     *     unit_id:?int,
     *     unit_name:string,
     *     unit_symbol:string,
     *     unit_multiplier:float,
     *     system_quantity_base:float,
     *     system_quantity_display:float,
     *     reorder_level:int,
     *     status:string,
     *     status_label:string,
     *     unit_cost:float,
     *     stock_value:float,
     *     display_stock:string
     * }>  $rows
     * @return array{total_skus:int, items_in_stock:int, low_stock_items:int, total_valuation:float}
     */
    public function summary(Collection $rows): array
    {
        return [
            'total_skus' => $rows->count(),
            'items_in_stock' => $rows->filter(fn (array $row): bool => (float) $row['system_quantity_base'] > 0)->count(),
            'low_stock_items' => $rows->filter(fn (array $row): bool => $row['status'] === 'low_stock')->count(),
            'total_valuation' => round($rows->sum(fn (array $row): float => (float) $row['stock_value']), 2),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function categoryOptions(): array
    {
        return ProductCategory::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public function vendorOptions(): array
    {
        return Vendor::query()->orderBy('name')->pluck('name', 'id')->all();
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
}
