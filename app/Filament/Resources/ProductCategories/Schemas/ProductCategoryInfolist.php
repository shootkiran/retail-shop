<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use App\Models\ProductCategory;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Category Overview')
                ->columnSpanFull()
                ->icon('heroicon-m-squares-2x2')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('name')->label('Category name'),
                            TextEntry::make('slug')->label('Slug')->badge(),
                            TextEntry::make('description')->label('Description')->columnSpanFull()->placeholder('No description provided'),
                        ]),
                ]),
            Section::make('Catalog Summary')
                ->columnSpanFull()
                ->icon('heroicon-m-archive-box')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('product_count')->label('Products')->state(fn (ProductCategory $record): int => $record->productItems()->count()),
                            TextEntry::make('active_products')->label('Active products')->state(fn (ProductCategory $record): int => $record->productItems()->where('is_active', true)->count()),
                            TextEntry::make('latest_product')
                                ->label('Latest product')
                                ->state(fn (ProductCategory $record) => $record->productItems()->latest('id')->value('name'))
                                ->placeholder('None'),
                        ]),
                ]),
        ]);
    }
}
