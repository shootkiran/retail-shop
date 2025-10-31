<?php

namespace App\Filament\Resources\Sales\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sale Details')
                ->columns(2)
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->columnSpan(1),
                    Select::make('payment_method_id')
                        ->label('Payment Method')
                        ->relationship('paymentMethod', 'name')
                        ->searchable()
                        ->preload()
                        ->columnSpan(1),
                    Select::make('payment_type')
                        ->options([
                            'paid' => 'Paid',
                            'credit' => 'Credit',
                        ])
                        ->required(),
                    Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                        ])
                        ->default('draft')
                        ->required(),
                    Select::make('payment_status')
                        ->options([
                            'pending' => 'Pending',
                            'partial' => 'Partial',
                            'paid' => 'Paid',
                        ])
                        ->default('pending')
                        ->required(),
                    DateTimePicker::make('sold_at')
                        ->label('Sale Date')
                        ->native(false)
                        ->seconds(false),
                    Textarea::make('notes')
                        ->columnSpanFull()
                        ->rows(3),
                ]),
            Section::make('Sale Items')
                ->columns(1)
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->columns(6)
                        ->collapsible()
                        ->reorderable()
                        ->createItemButtonLabel('Add product')
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
                            TextInput::make('unit_price')
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
                                ->dehydrated()
                                ->readOnly()
                                ->columnSpan(1),
                        ])
                        ->afterStateHydrated(fn (Set $set, Get $get) => self::updateTotals($get, $set))
                        ->afterStateUpdated(fn (Set $set, Get $get) => self::updateTotals($get, $set))
                        ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => $data),
                ]),
            Section::make('Payment Summary')
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
                    TextInput::make('order_discount')
                        ->numeric()
                        ->prefix('₦')
                        ->default(0)
                        ->minValue(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, Get $get) => self::updateTotals($get, $set)),
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
        $unitPrice = (float) $get('unit_price');
        $discount = (float) $get('discount_amount');

        return max(($quantity * $unitPrice) - $discount, 0);
    }

    protected static function updateTotals(Get $get, Set $set): void
    {
        $items = collect($get('items') ?? []);

        $lineTotal = $items->sum(fn ($item) => ((float) ($item['quantity'] ?? 0)) * ((float) ($item['unit_price'] ?? 0)));
        $lineDiscount = $items->sum(fn ($item) => (float) ($item['discount_amount'] ?? 0));
        $orderDiscount = (float) $get('order_discount');
        $tax = (float) $get('tax_amount');
        $amountPaid = (float) $get('amount_paid');

        $totalDiscount = $lineDiscount + $orderDiscount;
        $taxable = max($lineTotal - $totalDiscount, 0);

        $set('total_amount', $lineTotal);
        $set('discount_amount', $totalDiscount);

        $grandTotal = max($taxable + $tax, 0);
        $set('grand_total', $grandTotal);

        $set('amount_due', max($grandTotal - $amountPaid, 0));
    }
}
