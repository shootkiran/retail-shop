<?php

namespace App\Filament\Resources\ProductCategories;

use App\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Filament\Resources\ProductCategories\Schemas\ProductCategoryForm;
use App\Filament\Resources\ProductCategories\Tables\ProductCategoriesTable;
use App\Models\ProductCategory;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ProductCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductCategoriesTable::configure($table);
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
            'index' => ListProductCategories::route('/'),
            'create' => CreateProductCategory::route('/create'),
            'edit' => EditProductCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('productItems');
    }
}
