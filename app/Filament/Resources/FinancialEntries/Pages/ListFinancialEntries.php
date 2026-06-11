<?php

namespace App\Filament\Resources\FinancialEntries\Pages;

use App\Filament\Resources\FinancialEntries\FinancialEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFinancialEntries extends ListRecords
{
    protected static string $resource = FinancialEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
