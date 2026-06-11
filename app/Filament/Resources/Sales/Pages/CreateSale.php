<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\FinancialEntry;
use Filament\Resources\Pages\CreateRecord;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function afterCreate(): void
    {
        $this->record->loadMissing(['paymentMethod.settlementAccount', 'customer']);

        if ($this->record->amount_paid > 0 && $this->record->paymentMethod?->settlementAccount) {
            $account = $this->record->paymentMethod->settlementAccount;

            FinancialEntry::create([
                'accountable_type' => $account::class,
                'accountable_id' => $account->getKey(),
                'entry_type' => 'sale_receipt',
                'direction' => 'credit',
                'amount' => $this->record->amount_paid,
                'entry_date' => now()->toDateString(),
                'reference' => $this->record->reference,
                'notes' => 'Sale receipt from back-office form.',
            ]);
        }

        if ($this->record->amount_due > 0 && $this->record->customer) {
            $this->record->customer->increment('outstanding_balance', (float) $this->record->amount_due);
        }
    }

    protected function getRedirectUrl(): string
    {
        return route('sales.invoice', [
            'sale' => $this->record,
            'print' => 1,
        ]);
    }
}
