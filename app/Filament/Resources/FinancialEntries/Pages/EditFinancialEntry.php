<?php

namespace App\Filament\Resources\FinancialEntries\Pages;

use App\Filament\Resources\FinancialEntries\FinancialEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFinancialEntry extends EditRecord
{
    protected static string $resource = FinancialEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
