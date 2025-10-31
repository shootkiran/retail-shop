<?php

namespace App\Filament\Resources\ProductItems\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class ProductItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product Information')
                ->columns(3)
                ->schema([
                    TextInput::make('name')
                        ->columnSpan(2)
                        ->required()
                        ->maxLength(255),
                    TextInput::make('sku')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('barcode')
                        ->maxLength(255),
                    Select::make('product_category_id')
                        ->label('Category')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->columnSpan(2),
                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->relationship('vendor', 'name')
                        ->searchable()
                        ->preload(),
                    Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Inventory & Pricing')
                ->columns(3)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('unit_cost')
                                ->numeric()
                                ->prefix('रू')
                                ->minValue(0),
                            TextInput::make('unit_price')
                                ->numeric()
                                ->prefix('रू')
                                ->minValue(0),
                            TextInput::make('tax_rate')
                                ->label('Tax Rate')
                                ->numeric()
                                ->suffix('%')
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(0),
                            TextInput::make('stock_quantity')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                        ])
                        ->columnSpan(2),
                    TextInput::make('reorder_level')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),
        ]);
    }
}
