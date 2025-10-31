<?php

namespace App\Filament\Resources\Purchases\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Schema;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Purchase Details')
                ->columns(2)
                ->schema([
                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->relationship('vendor', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('payment_method_id')
                        ->label('Payment Method')
                        ->relationship('paymentMethod', 'name')
                        ->searchable()
                        ->preload(),
                    Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'ordered' => 'Ordered',
                            'received' => 'Received',
                            'cancelled' => 'Cancelled',
                        ])
                        ->default('draft')
                        ->required(),
                    DateTimePicker::make('purchased_at')
                        ->label('Purchase Date')
                        ->native(false)
                        ->seconds(false),
                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Line Items')
                ->columns(1)
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->columns(6)
                        ->collapsible()
                        ->reorderable()
                        ->createItemButtonLabel('Add item')
                        ->schema([
                            Select::make('product_item_id')
                                ->label('Product')
                                ->relationship('product', 'name')
                                ->searchable()
                                ->preload()
                                ->columnSpan(2)
                                ->required(),
                            TextInput::make('quantity')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Set $set, Get $get) => $set('total_amount', self::calculateLineTotal($get)))
                                ->columnSpan(1),
                            TextInput::make('unit_cost')
                                ->numeric()
                                ->prefix('₦')
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Set $set, Get $get) => $set('total_amount', self::calculateLineTotal($get)))
                                ->columnSpan(1),
                            TextInput::make('discount_amount')
                                ->numeric()
                                ->prefix('₦')
                                ->default(0)
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Set $set, Get $get) => $set('total_amount', self::calculateLineTotal($get)))
                                ->columnSpan(1),
                            TextInput::make('total_amount')
                                ->numeric()
                                ->prefix('₦')
                                ->default(0)
                                ->readOnly()
                                ->dehydrated()
                                ->columnSpan(1),
                        ])
                        ->afterStateHydrated(fn (Set $set, Get $get) => self::updateTotals($get, $set))
                        ->afterStateUpdated(fn (Set $set, Get $get) => self::updateTotals($get, $set)),
                ]),
            Section::make('Financial Summary')
                ->columns(3)
                ->schema([
                    TextInput::make('total_amount')
                        ->numeric()
                        ->prefix('₦')
                        ->readOnly()
                        ->dehydrated(),
                    TextInput::make('discount_amount')
                        ->numeric()
                        ->prefix('₦')
                        ->readOnly()
                        ->dehydrated(),
                    TextInput::make('tax_amount')
                        ->numeric()
                        ->prefix('₦')
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, Get $get) => self::updateTotals($get, $set)),
                    TextInput::make('grand_total')
                        ->numeric()
                        ->prefix('₦')
                        ->readOnly()
                        ->dehydrated(),
                    TextInput::make('amount_paid')
                        ->numeric()
                        ->prefix('₦')
                        ->default(0)
                        ->minValue(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, Get $get) => self::updateTotals($get, $set)),
                    TextInput::make('amount_due')
                        ->numeric()
                        ->prefix('₦')
                        ->readOnly()
                        ->dehydrated(),
                ]),
        ]);
    }

    protected static function calculateLineTotal(Get $get): float
    {
        $quantity = (float) $get('quantity');
        $unitCost = (float) $get('unit_cost');
        $discount = (float) $get('discount_amount');

        return max(($quantity * $unitCost) - $discount, 0);
    }

    protected static function updateTotals(Get $get, Set $set): void
    {
        $items = collect($get('items') ?? []);

        $lineTotal = $items->sum(fn ($item) => ((float) ($item['quantity'] ?? 0)) * ((float) ($item['unit_cost'] ?? 0)));
        $lineDiscount = $items->sum(fn ($item) => (float) ($item['discount_amount'] ?? 0));
        $tax = (float) $get('tax_amount');
        $amountPaid = (float) $get('amount_paid');

        $set('total_amount', $lineTotal);
        $set('discount_amount', $lineDiscount);

        $grandTotal = max($lineTotal - $lineDiscount + $tax, 0);
        $set('grand_total', $grandTotal);

        $set('amount_due', max($grandTotal - $amountPaid, 0));
    }
}
