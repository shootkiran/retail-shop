<?php

namespace App\Filament\Resources\ProductItems;

use App\Filament\Resources\ProductItems\Pages\CreateProductItem;
use App\Filament\Resources\ProductItems\Pages\EditProductItem;
use App\Filament\Resources\ProductItems\Pages\ListProductItems;
use App\Filament\Resources\ProductItems\Schemas\ProductItemForm;
use App\Filament\Resources\ProductItems\Tables\ProductItemsTable;
use App\Models\ProductItem;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductItemResource extends Resource
{
    protected static ?string $model = ProductItem::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCube;

    protected static UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ProductItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductItems::route('/'),
            'create' => CreateProductItem::route('/create'),
            'edit' => EditProductItem::route('/{record}/edit'),
        ];
    }
}
