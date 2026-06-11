<?php

namespace App\Filament\Resources\FinancialEntries\Pages;

use App\Filament\Resources\FinancialEntries\FinancialEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinancialEntry extends CreateRecord
{
    protected static string $resource = FinancialEntryResource::class;
}
