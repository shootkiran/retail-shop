<?php

namespace App\Filament\Resources\ProductItems\Pages;

use App\Filament\Resources\ProductItems\ProductItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductItem extends EditRecord
{
    protected static string $resource = ProductItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
