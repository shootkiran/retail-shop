<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Models\Sale;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sale Overview')
                ->columnSpanFull()
                ->icon('heroicon-m-receipt-percent')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('reference')->label('Reference')->badge(),
                            TextEntry::make('customer.name')->label('Customer')->placeholder('Walk-in customer'),
                            TextEntry::make('paymentMethod.name')->label('Payment method')->placeholder('Not set'),
                            TextEntry::make('status')->label('Status')->badge(),
                            TextEntry::make('payment_status')->label('Payment status')->badge(),
                            TextEntry::make('payment_type')->label('Payment type')->badge(),
                            TextEntry::make('sold_at')->label('Sold at')->dateTime(),
                            TextEntry::make('delivery_option')->label('Delivery option')->placeholder('No delivery'),
                            TextEntry::make('notes')->label('Notes')->columnSpanFull()->placeholder('No notes'),
                        ]),
                ]),
            Section::make('Totals')
                ->columnSpanFull()
                ->icon('heroicon-m-banknotes')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('total_amount')->label('Subtotal')->money('NPR')->state(fn (Sale $record): float => (float) $record->total_amount),
                            TextEntry::make('discount_amount')->label('Discount')->money('NPR')->state(fn (Sale $record): float => (float) $record->discount_amount),
                            TextEntry::make('tax_amount')->label('Tax')->money('NPR')->state(fn (Sale $record): float => (float) $record->tax_amount),
                            TextEntry::make('delivery_charge')->label('Delivery charge')->money('NPR')->state(fn (Sale $record): float => (float) $record->delivery_charge),
                            TextEntry::make('grand_total')->label('Grand total')->money('NPR')->state(fn (Sale $record): float => (float) $record->grand_total),
                            TextEntry::make('amount_paid')->label('Amount paid')->money('NPR')->state(fn (Sale $record): float => (float) $record->amount_paid),
                            TextEntry::make('amount_due')->label('Amount due')->money('NPR')->state(fn (Sale $record): float => (float) $record->amount_due)->color(fn (Sale $record): string => (float) $record->amount_due > 0 ? 'danger' : 'success'),
                            TextEntry::make('items_count')->label('Lines')->state(fn (Sale $record): int => $record->items()->count()),
                        ]),
                ]),
            Section::make('Items')
                ->columnSpanFull()
                ->icon('heroicon-m-clipboard-document-list')
                ->schema([
                    RepeatableEntry::make('items')
                        ->schema([
                            Grid::make(5)
                                ->schema([
                                    TextEntry::make('product')->label('Product'),
                                    TextEntry::make('unit')->label('Unit')->placeholder('pcs'),
                                    TextEntry::make('quantity')->label('Qty'),
                                    TextEntry::make('unit_price')->label('Price')->money('NPR'),
                                    TextEntry::make('total_amount')->label('Line total')->money('NPR'),
                                ]),
                        ])
                        ->state(fn (Sale $record): array => $record->items->map(fn ($item): array => [
                            'product' => $item->product?->name ?? 'Unknown product',
                            'unit' => $item->unit?->symbol ?? $item->unit?->name ?? 'pcs',
                            'quantity' => number_format((float) $item->quantity, 2),
                            'unit_price' => (float) $item->unit_price,
                            'total_amount' => (float) $item->total_amount,
                        ])->all()),
                ]),
        ]);
    }
}
