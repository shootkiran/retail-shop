<?php

namespace App\Filament\Resources\ProductItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sku')
                    ->badge()
                    ->copyable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->toggleable(),
                TextColumn::make('unit_price')
                    ->label('Price')
                    ->money('ngn')
                    ->sortable(),
                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                SelectFilter::make('product_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name'),
                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All statuses'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
