<?php

namespace App\Filament\Resources\BusinessSettings\Pages;

use App\Filament\Resources\BusinessSettings\BusinessSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListBusinessSettings extends ListRecords
{
    protected static string $resource = BusinessSettingResource::class;
}
