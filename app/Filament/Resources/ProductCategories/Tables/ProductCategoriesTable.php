<?php

namespace App\Filament\Resources\ProductCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->copyable(),
                BadgeColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->visible(fn ($record) => filled($record->description)),
                TextColumn::make('productItems_count')
                    ->label('Products')
                    ->counts('productItems')
                    ->badge(),
            ])
            ->filters([
                Filter::make('has_products')
                    ->label('Has products')
                    ->query(fn (Builder $query): Builder => $query->whereHas('productItems')),
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
