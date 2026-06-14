<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Resources\CreditNoteResource\Pages\CreateCreditNote;
use App\Filament\Resources\CreditNoteResource\Pages\EditCreditNote;
use App\Filament\Resources\CreditNoteResource\Pages\ListCreditNotes;
use App\Filament\Resources\CreditNoteResource\Pages\ViewCreditNote;
use App\Models\Accounting\CreditNote;
use App\Models\Sale;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CreditNoteResource extends Resource
{
    use RequiresBackOffice;

    protected static ?string $model = CreditNote::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Credit Notes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Credit Note Details')
                    ->columns(2)
                    ->schema([
                        Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('sale_id')
                            ->label('Sale (Invoice) Reference')
                            ->options(fn (): array => Sale::query()->orderBy('reference')->pluck('reference', 'id')->all())
                            ->searchable()
                            ->preload(),
                        TextInput::make('reference')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('refunded_at')
                            ->required()
                            ->default(now()),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Returned Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->columns(5)
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
                                    ->required(),
                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('रू')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => $set('total_amount', self::calculateLineTotal($get)))
                                    ->required(),
                                TextInput::make('tax_amount')
                                    ->numeric()
                                    ->prefix('रू')
                                    ->default(0.00)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => $set('total_amount', self::calculateLineTotal($get))),
                                TextInput::make('total_amount')
                                    ->numeric()
                                    ->prefix('रू')
                                    ->readOnly()
                                    ->dehydrated(),
                            ])
                            ->afterStateHydrated(fn (Set $set, Get $get) => self::updateTotals($get, $set))
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::updateTotals($get, $set)),
                    ]),
                Section::make('Summary')
                    ->columns(4)
                    ->schema([
                        TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('रू')
                            ->readOnly(),
                        TextInput::make('discount_amount')
                            ->numeric()
                            ->prefix('रू')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::updateTotals($get, $set)),
                        TextInput::make('tax_amount')
                            ->numeric()
                            ->prefix('रू')
                            ->readOnly(),
                        TextInput::make('grand_total')
                            ->numeric()
                            ->prefix('रू')
                            ->readOnly(),
                    ]),
            ]);
    }

    protected static function calculateLineTotal(Get $get): float
    {
        $qty = (float) $get('quantity');
        $price = (float) $get('unit_price');
        $tax = (float) $get('tax_amount');

        return round(($qty * $price) + $tax, 2);
    }

    protected static function updateTotals(Get $get, Set $set): void
    {
        $items = collect($get('items') ?? []);
        $lineTotal = $items->sum(fn ($i) => ((float) ($i['quantity'] ?? 0)) * ((float) ($i['unit_price'] ?? 0)));
        $taxTotal = $items->sum(fn ($i) => (float) ($i['tax_amount'] ?? 0));
        $discount = (float) $get('discount_amount');

        $set('total_amount', round($lineTotal, 2));
        $set('tax_amount', round($taxTotal, 2));

        $grandTotal = max($lineTotal - $discount + $taxTotal, 0.00);
        $set('grand_total', round($grandTotal, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sale.reference')
                    ->label('Invoice Reference')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('refunded_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->money('NPR')
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditNotes::route('/'),
            'create' => CreateCreditNote::route('/create'),
            'view' => ViewCreditNote::route('/{record}'),
            'edit' => EditCreditNote::route('/{record}/edit'),
        ];
    }
}
