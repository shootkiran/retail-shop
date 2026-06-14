<?php

namespace App\Filament\Resources\ProductItems;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Resources\ProductItems\Pages\CreateProductItem;
use App\Filament\Resources\ProductItems\Pages\EditProductItem;
use App\Filament\Resources\ProductItems\Pages\ListProductItems;
use App\Filament\Resources\ProductItems\Pages\ViewProductItem;
use App\Filament\Resources\ProductItems\Schemas\ProductItemForm;
use App\Filament\Resources\ProductItems\Schemas\ProductItemInfolist;
use App\Filament\Resources\ProductItems\Tables\ProductItemsTable;
use App\Models\ProductItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductItemResource extends Resource
{
    use RequiresBackOffice;

    protected static ?string $model = ProductItem::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCube;

    protected static UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ProductItemForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductItemInfolist::configure($schema);
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
            'view' => ViewProductItem::route('/{record}'),
            'create' => CreateProductItem::route('/create'),
            'edit' => EditProductItem::route('/{record}/edit'),
        ];
    }
}
