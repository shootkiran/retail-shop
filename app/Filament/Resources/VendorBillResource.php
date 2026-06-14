<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Resources\VendorBillResource\Pages\CreateVendorBill;
use App\Filament\Resources\VendorBillResource\Pages\EditVendorBill;
use App\Filament\Resources\VendorBillResource\Pages\ListVendorBills;
use App\Filament\Resources\VendorBillResource\Pages\ViewVendorBill;
use App\Models\Accounting\VendorBill;
use App\Models\Accounting\VendorBillPayment;
use App\Models\BankAccount;
use App\Models\CashRegister;
use BackedEnum;
use Filament\Actions\Action;
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
use Illuminate\Support\Str;
use UnitEnum;

class VendorBillResource extends Resource
{
    use RequiresBackOffice;

    protected static ?string $model = VendorBill::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Vendor Bills';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Bill Details')
                    ->columns(2)
                    ->schema([
                        Select::make('vendor_id')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('reference')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('bill_date')
                            ->required()
                            ->default(now()),
                        DatePicker::make('due_date'),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'posted' => 'Posted',
                                'void' => 'Voided',
                            ])
                            ->default('draft')
                            ->required(),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Line Items')
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
                                    ->afterStateUpdated(
                                        fn (Set $set, Get $get) =>
                                            $set('total_amount', self::calculateLineTotal($get))
                                    )
                                    ->required(),
                                TextInput::make('unit_cost')
                                    ->numeric()
                                    ->prefix('रू')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Set $set, Get $get) =>
                                            $set('total_amount', self::calculateLineTotal($get))
                                    )
                                    ->required(),
                                TextInput::make('tax_amount')
                                    ->numeric()
                                    ->prefix('रू')
                                    ->default(0.00)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Set $set, Get $get) =>
                                            $set('total_amount', self::calculateLineTotal($get))
                                    ),
                                TextInput::make('total_amount')
                                    ->numeric()
                                    ->prefix('रू')
                                    ->readOnly()
                                    ->dehydrated(),
                            ])
                            ->afterStateHydrated(fn (Set $set, Get $get) => self::updateTotals($get, $set))
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::updateTotals($get, $set)),
                    ]),
                Section::make('Financial Summary')
                    ->columns(3)
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
                        TextInput::make('amount_paid')
                            ->numeric()
                            ->prefix('रू')
                            ->readOnly(),
                        TextInput::make('amount_due')
                            ->numeric()
                            ->prefix('रू')
                            ->readOnly(),
                    ]),
            ]);
    }

    protected static function calculateLineTotal(Get $get): float
    {
        $qty = (float) $get('quantity');
        $cost = (float) $get('unit_cost');
        $tax = (float) $get('tax_amount');

        return round(($qty * $cost) + $tax, 2);
    }

    protected static function updateTotals(Get $get, Set $set): void
    {
        $items = collect($get('items') ?? []);
        $lineTotal = $items->sum(fn ($i) => ((float) ($i['quantity'] ?? 0)) * ((float) ($i['unit_cost'] ?? 0)));
        $taxTotal = $items->sum(fn ($i) => (float) ($i['tax_amount'] ?? 0));
        $discount = (float) $get('discount_amount');
        $amountPaid = (float) $get('amount_paid');

        $set('total_amount', round($lineTotal, 2));
        $set('tax_amount', round($taxTotal, 2));

        $grandTotal = max($lineTotal - $discount + $taxTotal, 0.00);
        $set('grand_total', round($grandTotal, 2));
        $set('amount_due', round(max($grandTotal - $amountPaid, 0.00), 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bill_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'posted',
                        'success' => 'paid',
                        'warning' => 'partially_paid',
                        'danger' => 'void',
                    ]),
                TextColumn::make('grand_total')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('amount_due')
                    ->money('NPR')
                    ->sortable(),
            ])
            ->actions([
                Action::make('recordPayment')
                    ->label('Pay')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(fn (VendorBill $record): bool => in_array($record->status, ['posted', 'partially_paid']) && $record->amount_due > 0)
                    ->form([
                        DatePicker::make('payment_date')
                            ->required()
                            ->default(now()),
                        Select::make('account_type')
                            ->label('Payment Account Type')
                            ->options([
                                BankAccount::class => 'Bank Account',
                                CashRegister::class => 'Cash Register',
                            ])
                            ->live()
                            ->required(),
                        Select::make('account_id')
                            ->label('Account')
                            ->options(fn (Get $get): array => match ($get('account_type')) {
                                BankAccount::class => BankAccount::query()->orderBy('name')->pluck('name', 'id')->all(),
                                CashRegister::class => CashRegister::query()->orderBy('name')->pluck('name', 'id')->all(),
                                default => [],
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('amount')
                            ->numeric()
                            ->prefix('रू')
                            ->required()
                            ->maxValue(fn (VendorBill $record) => $record->amount_due)
                            ->default(fn (VendorBill $record) => $record->amount_due),
                        TextInput::make('reference')
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->rows(3),
                    ])
                    ->action(function (VendorBill $record, array $data): void {
                        $payment = new VendorBillPayment();
                        $payment->business_id = $record->business_id;
                        $payment->vendor_bill_id = $record->id;
                        $payment->amount = $data['amount'];
                        $payment->payment_date = $data['payment_date'];
                        $payment->reference = $data['reference'] ?: 'VBP-' . Str::upper(Str::ulid());
                        $payment->notes = $data['notes'];

                        if ($data['account_type'] === BankAccount::class) {
                            $payment->bank_account_id = $data['account_id'];
                        } else {
                            $payment->cash_register_id = $data['account_id'];
                        }

                        $payment->save();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorBills::route('/'),
            'create' => CreateVendorBill::route('/create'),
            'view' => ViewVendorBill::route('/{record}'),
            'edit' => EditVendorBill::route('/{record}/edit'),
        ];
    }
}
