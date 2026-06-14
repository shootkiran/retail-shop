<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresBackOffice;
use App\Models\StockAdjustmentBatch;
use App\Models\User;
use App\Services\InventoryCountSheetService;
use App\Services\StockAdjustmentService;
use App\Support\CurrentBusiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class StockAdjustments extends Page
{
    use RequiresBackOffice;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';

    protected static UnitEnum|string|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Stock Adjustment';

    protected string $view = 'filament.pages.stock-adjustments';

    public ?int $categoryId = null;

    public ?int $vendorId = null;

    public string $stockStatus = 'all';

    public string $search = '';

    public string $postingMode = 'inventory_only';

    public ?string $countedAt = null;

    public ?string $reference = null;

    public ?string $notes = null;

    public ?string $varianceReason = null;

    /**
     * @var array<int, array{
     *     counted_quantity:?float,
     *     notes:?string,
     *     name:string,
     *     category:?string,
     *     vendor:?string,
     *     unit_label:string,
     *     system_quantity_base:float,
     *     system_quantity_display:float,
     *     status:string,
     *     status_label:string
     * }>
     */
    public array $lines = [];

    public function mount(): void
    {
        $this->countedAt ??= now()->toDateString();
        $this->loadLines();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Download Count Sheet')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('inventory.count-sheet.pdf', $this->filtersQueryParameters()))
                ->openUrlInNewTab(),
        ];
    }

    public function loadLines(): void
    {
        $this->lines = app(InventoryCountSheetService::class)
            ->rows($this->filtersQueryParameters())
            ->mapWithKeys(function (array $row): array {
                return [
                    $row['product_id'] => [
                        'counted_quantity' => null,
                        'notes' => null,
                        'name' => $row['name'],
                        'category' => $row['category'],
                        'vendor' => $row['vendor'],
                        'unit_label' => $row['unit_symbol'],
                        'system_quantity_base' => $row['system_quantity_base'],
                        'system_quantity_display' => $row['system_quantity_display'],
                        'status' => $row['status'],
                        'status_label' => $row['status_label'],
                    ],
                ];
            })
            ->all();
    }

    public function saveAdjustment(): void
    {
        $validated = $this->validate([
            'countedAt' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'postingMode' => ['required', 'in:inventory_only,inventory_and_daybook'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'varianceReason' => ['nullable', 'string', 'max:5000'],
            'lines' => ['array'],
            'lines.*.counted_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $lines = [];

        foreach ((array) ($validated['lines'] ?? []) as $productId => $line) {
            if (filled($line['counted_quantity'] ?? null)) {
                $lines[$productId] = $line;
            }
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Enter at least one counted quantity before saving the adjustment.',
            ]);
        }

        $business = app(CurrentBusiness::class)->get();

        if (! $business) {
            throw ValidationException::withMessages([
                'lines' => 'No active business is available for this adjustment.',
            ]);
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'lines' => 'No authenticated user is available to post this adjustment.',
            ]);
        }

        $batchPayload = [
            'counted_at' => $validated['countedAt'],
            'reference' => $validated['reference'] ?? null,
            'posting_mode' => $validated['postingMode'],
            'notes' => $validated['notes'] ?? null,
            'variance_reason' => $validated['varianceReason'] ?? null,
            'lines' => $lines,
        ];

        $batch = app(StockAdjustmentService::class)->createBatch($business, $user, $batchPayload);

        $this->reference = $batch->reference;
        $this->loadLines();

        Notification::make()
            ->title('Stock adjustment posted')
            ->body('Reference: '.$batch->reference)
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, StockAdjustmentBatch>
     */
    public function getRecentBatchesProperty(): Collection
    {
        return StockAdjustmentBatch::query()
            ->latest('posted_at')
            ->latest('id')
            ->limit(10)
            ->get();
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
