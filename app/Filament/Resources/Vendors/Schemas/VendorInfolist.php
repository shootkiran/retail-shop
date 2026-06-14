<?php

namespace App\Filament\Resources\Vendors\Schemas;

use App\Models\Vendor;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vendor Profile')
                ->columnSpanFull()
                ->icon('heroicon-m-building-storefront')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('name')->label('Vendor name'),
                            TextEntry::make('contact_person')->label('Contact person')->placeholder('Not set'),
                            TextEntry::make('email')->label('Email')->placeholder('Not provided'),
                            TextEntry::make('phone')->label('Phone')->placeholder('Not provided'),
                            TextEntry::make('address')->label('Address')->columnSpanFull()->placeholder('No address provided'),
                        ]),
                ]),
            Section::make('Purchase Summary')
                ->columnSpanFull()
                ->icon('heroicon-m-shopping-cart')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('purchases_count')->label('Purchases')->state(fn (Vendor $record): int => $record->purchases()->count()),
                            TextEntry::make('products_count')->label('Products supplied')->state(fn (Vendor $record): int => $record->productItems()->count()),
                            TextEntry::make('total_spend')->label('Total spend')->money('NPR')->state(fn (Vendor $record): float => (float) $record->purchases()->sum('grand_total')),
                            TextEntry::make('outstanding_due')->label('Outstanding due')->money('NPR')->state(fn (Vendor $record): float => (float) $record->purchases()->sum('amount_due'))->color(fn (Vendor $record): string => (float) $record->purchases()->sum('amount_due') > 0 ? 'warning' : 'success'),
                        ]),
                ]),
        ]);
    }
}
