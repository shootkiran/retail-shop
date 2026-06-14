<?php

namespace App\Filament\Resources\ProductItems\Schemas;

use App\Models\ProductItem;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product Overview')
                ->columnSpanFull()
                ->icon('heroicon-m-cube')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('name')->label('Product name'),
                            TextEntry::make('sku')->label('SKU')->badge(),
                            TextEntry::make('barcode')->label('Barcode')->placeholder('Not set'),
                            TextEntry::make('is_active')
                                ->label('Status')
                                ->badge()
                                ->state(fn (ProductItem $record): string => $record->is_active ? 'Active' : 'Inactive')
                                ->color(fn (ProductItem $record): string => $record->is_active ? 'success' : 'gray'),
                            TextEntry::make('description')->label('Description')->columnSpanFull()->placeholder('No description provided'),
                        ]),
                ]),
            Section::make('Classification')
                ->columnSpanFull()
                ->icon('heroicon-m-tag')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('category.name')->label('Category')->placeholder('Unassigned'),
                            TextEntry::make('vendor.name')->label('Vendor')->placeholder('Unassigned'),
                            TextEntry::make('baseUnit.name')->label('Base unit')->placeholder('Piece'),
                        ]),
                ]),
            Section::make('Pricing & Stock')
                ->columnSpanFull()
                ->icon('heroicon-m-scale')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('unit_cost')->label('Cost price')->money('NPR')->state(fn (ProductItem $record): float => (float) $record->unit_cost),
                            TextEntry::make('unit_price')->label('Sale price')->money('NPR')->state(fn (ProductItem $record): float => (float) $record->unit_price),
                            TextEntry::make('tax_rate')->label('Tax %')->state(fn (ProductItem $record): string => number_format((float) $record->tax_rate, 2).'%'),
                            TextEntry::make('reorder_level')->label('Reorder level')->state(fn (ProductItem $record): string => number_format((float) $record->reorder_level, 0)),
                            TextEntry::make('stock_display')->label('Current stock')->state(fn (ProductItem $record): string => $record->stock_display),
                            TextEntry::make('stock_quantity')->label('Base quantity')->state(fn (ProductItem $record): string => number_format((float) $record->stock_quantity, 4)),
                            TextEntry::make('sales_count')->label('Sale lines')->state(fn (ProductItem $record): int => $record->saleItems()->count()),
                            TextEntry::make('purchase_count')->label('Purchase lines')->state(fn (ProductItem $record): int => $record->purchaseItems()->count()),
                        ]),
                ]),
        ]);
    }
}
