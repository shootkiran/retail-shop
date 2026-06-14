<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\FinancialEntry;
use App\Models\ProductItem;
use App\Models\StockAdjustmentBatch;
use App\Models\StockAdjustmentLine;
use App\Models\User;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StockAdjustmentService
{
    /**
     * @param  array{
     *     counted_at?: string|null,
     *     reference?: string|null,
     *     posting_mode?: string,
     *     notes?: string|null,
     *     variance_reason?: string|null,
     *     lines: array<int|string, array<string, mixed>>
     * }  $payload
     */
    public function createBatch(Business $business, User $user, array $payload): StockAdjustmentBatch
    {
        $postingMode = (string) ($payload['posting_mode'] ?? 'inventory_only');
        $countedAt = filled($payload['counted_at'] ?? null)
            ? Carbon::parse((string) $payload['counted_at'])
            : now();
        $reference = blank($payload['reference'] ?? null)
            ? 'STK-'.Str::upper(Str::ulid())
            : (string) $payload['reference'];

        $batch = StockAdjustmentBatch::query()->create([
            'business_id' => $business->getKey(),
            'reference' => $reference,
            'counted_by_user_id' => $user->getKey(),
            'posted_by_user_id' => $user->getKey(),
            'counted_at' => $countedAt,
            'posted_at' => now(),
            'posting_mode' => $postingMode,
            'status' => 'posted',
            'variance_value' => 0,
            'notes' => $payload['notes'] ?? null,
            'variance_reason' => $payload['variance_reason'] ?? null,
        ]);

        $postingAccount = $postingMode === 'inventory_and_daybook'
            ? $this->resolvePostingAccount($business)
            : null;

        $totalVarianceValue = 0.0;
        $totalGain = 0.0;
        $totalLoss = 0.0;

        foreach ($payload['lines'] as $productId => $lineData) {
            $countedQuantity = $lineData['counted_quantity'] ?? null;

            if ($countedQuantity === null || $countedQuantity === '') {
                continue;
            }

            $product = ProductItem::query()
                ->with('baseUnit:id,name,symbol,multiplier_to_base')
                ->whereKey((int) $productId)
                ->firstOrFail();

            $unit = $product->baseUnit;
            $systemQuantityBase = (float) $product->stock_quantity;
            $systemQuantityDisplay = $unit ? $unit->fromBase($systemQuantityBase) : $systemQuantityBase;
            $countedQuantity = (float) $countedQuantity;
            $countedQuantityBase = $unit ? $unit->toBase($countedQuantity) : $countedQuantity;
            $varianceBase = round($countedQuantityBase - $systemQuantityBase, 4);
            $varianceValue = round($varianceBase * (float) $product->unit_cost, 2);

            StockAdjustmentLine::query()->create([
                'business_id' => $business->getKey(),
                'stock_adjustment_batch_id' => $batch->getKey(),
                'product_item_id' => $product->getKey(),
                'unit_id' => $unit?->getKey(),
                'unit_name_snapshot' => $unit?->name ?: 'Piece',
                'unit_symbol_snapshot' => $unit?->symbol ?: 'pcs',
                'unit_multiplier_snapshot' => max((float) ($unit?->multiplier_to_base ?? 1), 1.0),
                'system_quantity_base' => $systemQuantityBase,
                'system_quantity_display' => $systemQuantityDisplay,
                'counted_quantity' => $countedQuantity,
                'counted_quantity_base' => $countedQuantityBase,
                'variance_base' => $varianceBase,
                'variance_value' => $varianceValue,
                'unit_cost' => (float) $product->unit_cost,
                'notes' => $lineData['notes'] ?? null,
            ]);

            $product->forceFill([
                'stock_quantity' => $countedQuantityBase,
            ])->saveQuietly();

            if ($varianceValue > 0) {
                $totalGain += $varianceValue;
            } else {
                $totalLoss += abs($varianceValue);
            }

            if ($postingAccount && $varianceValue !== 0.0) {
                FinancialEntry::query()->create([
                    'accountable_type' => $postingAccount::class,
                    'accountable_id' => $postingAccount->getKey(),
                    'entry_type' => $varianceValue > 0 ? 'stock_adjustment_gain' : 'stock_adjustment_loss',
                    'direction' => $varianceValue > 0 ? 'credit' : 'debit',
                    'amount' => abs($varianceValue),
                    'entry_date' => $countedAt->toDateString(),
                    'reference' => $reference,
                    'notes' => trim(($product->name.' stock adjustment')."\n".($payload['variance_reason'] ?? '')),
                ]);
            }

            $totalVarianceValue += $varianceValue;
        }

        if ($postingMode === 'inventory_and_daybook') {
            $service = app(JournalEntryService::class);
            $inventoryAccount = $service->getOrCreateAccount($business, 'asset', 'Inventory', '1210', 'Merchandise Inventory', 'Inventory held for sale.');
            $gainAccount = $service->getOrCreateAccount($business, 'revenue', 'Other Operating Revenue', '4230', 'Stock Adjustment Gain', 'Gains from physical stock count variances.');
            $lossAccount = $service->getOrCreateAccount($business, 'expense', 'Cost of Goods Sold', '5030', 'Stock Variance Expense', 'Losses from physical stock count variances.');

            $lines = [];

            if ($totalGain > 0) {
                $lines[] = [
                    'account_id' => $inventoryAccount->id,
                    'debit' => round($totalGain, 2),
                    'credit' => 0.00,
                    'notes' => 'Stock adjustment gains for batch '.$reference,
                ];
                $lines[] = [
                    'account_id' => $gainAccount->id,
                    'debit' => 0.00,
                    'credit' => round($totalGain, 2),
                    'notes' => 'Stock adjustment gains for batch '.$reference,
                ];
            }

            if ($totalLoss > 0) {
                $lines[] = [
                    'account_id' => $lossAccount->id,
                    'debit' => round($totalLoss, 2),
                    'credit' => 0.00,
                    'notes' => 'Stock adjustment losses for batch '.$reference,
                ];
                $lines[] = [
                    'account_id' => $inventoryAccount->id,
                    'debit' => 0.00,
                    'credit' => round($totalLoss, 2),
                    'notes' => 'Stock adjustment losses for batch '.$reference,
                ];
            }

            if (count($lines) > 0) {
                $service->createEntry(
                    $business,
                    $countedAt,
                    $reference,
                    'Stock adjustment batch '.$reference.'. Reason: '.($payload['variance_reason'] ?? 'None'),
                    $lines,
                    $batch
                );
            }
        }

        $batch->forceFill([
            'variance_value' => round($totalVarianceValue, 2),
        ])->saveQuietly();

        return $batch->load(['lines.product', 'lines.unit', 'countedBy', 'postedBy']);
    }

    protected function resolvePostingAccount(Business $business): BankAccount|CashRegister|null
    {
        $cashRegister = CashRegister::query()
            ->where('business_id', $business->getKey())
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($cashRegister) {
            return $cashRegister;
        }

        $bankAccount = BankAccount::query()
            ->where('business_id', $business->getKey())
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($bankAccount) {
            return $bankAccount;
        }

        return null;
    }
}
