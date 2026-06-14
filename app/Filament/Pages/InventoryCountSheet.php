<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresBackOffice;
use App\Services\InventoryCountSheetService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class InventoryCountSheet extends Page
{
    use RequiresBackOffice;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static UnitEnum|string|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Inventory Count Sheet';

    protected string $view = 'filament.pages.inventory-count-sheet';

    public ?int $categoryId = null;

    public ?int $vendorId = null;

    public string $stockStatus = 'all';

    public string $search = '';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('inventory.count-sheet.pdf', $this->filtersQueryParameters()))
                ->openUrlInNewTab(),
            Action::make('stockAdjustment')
                ->label('Stock Adjustment')
                ->icon('heroicon-o-scale')
                ->url(fn (): string => StockAdjustments::getUrl()),
        ];
    }

    /**
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
    public function getRowsProperty(): Collection
    {
        return app(InventoryCountSheetService::class)->rows($this->filtersQueryParameters());
    }

    /**
     * @return array{total_skus:int, items_in_stock:int, low_stock_items:int, total_valuation:float}
     */
    public function getSummaryProperty(): array
    {
        $rows = app(InventoryCountSheetService::class)->rows($this->filtersQueryParameters());

        return app(InventoryCountSheetService::class)->summary($rows);
    }

    /**
     * @return array<int, string>
     */
    public function getCategoryOptionsProperty(): array
    {
        return app(InventoryCountSheetService::class)->categoryOptions();
    }

    /**
     * @return array<int, string>
     */
    public function getVendorOptionsProperty(): array
    {
        return app(InventoryCountSheetService::class)->vendorOptions();
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return config('retail.currency.symbol', 'रू').' '.number_format((float) $amount, 2);
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

    /**
     * @return array<string, int|string>
     */
    protected function filtersQueryParameters(): array
    {
        $filters = [];

        if (filled($this->search)) {
            $filters['search'] = $this->search;
        }

        if ($this->categoryId) {
            $filters['category_id'] = $this->categoryId;
        }

        if ($this->vendorId) {
            $filters['vendor_id'] = $this->vendorId;
        }

        if ($this->stockStatus !== 'all') {
            $filters['stock_status'] = $this->stockStatus;
        }

        return $filters;
    }
}
