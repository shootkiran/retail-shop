<?php

namespace App\Filament\Resources\FinancialEntries;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Resources\FinancialEntries\Pages\CreateFinancialEntry;
use App\Filament\Resources\FinancialEntries\Pages\EditFinancialEntry;
use App\Filament\Resources\FinancialEntries\Pages\ListFinancialEntries;
use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Models\FinancialEntry;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class FinancialEntryResource extends Resource
{
    use RequiresBackOffice;

    protected static ?string $model = FinancialEntry::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Daybook';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('entry_date')
                ->required()
                ->default(now()),
            Select::make('entry_type')
                ->options([
                    'sale_receipt' => 'Sale Receipt',
                    'customer_payment' => 'Customer Payment',
                    'bank_deposit' => 'Bank Deposit',
                    'bank_withdrawal' => 'Bank Withdrawal',
                    'transfer_in' => 'Transfer In',
                    'transfer_out' => 'Transfer Out',
                    'vendor_payment' => 'Vendor Payment',
                    'manual' => 'Manual Adjustment',
                ])
                ->required(),
            Select::make('direction')
                ->options([
                    'credit' => 'Credit',
                    'debit' => 'Debit',
                ])
                ->required(),
            Select::make('accountable_type')
                ->label('Account Type')
                ->options([
                    BankAccount::class => 'Bank Account',
                    CashRegister::class => 'Cash Register',
                ])
                ->live()
                ->required(),
            Select::make('accountable_id')
                ->label('Account')
                ->options(fn (Get $get): array => match ($get('accountable_type')) {
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
                ->required(),
            TextInput::make('reference')
                ->maxLength(255),
            Textarea::make('notes')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('entry_type')
                    ->badge()
                    ->sortable(),
                BadgeColumn::make('direction')
                    ->colors([
                        'success' => 'credit',
                        'danger' => 'debit',
                    ]),
                TextColumn::make('accountable.name')
                    ->label('Account')
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('reference')
                    ->toggleable(),
                TextColumn::make('notes')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('entry_type')
                    ->options([
                        'sale_receipt' => 'Sale Receipt',
                        'customer_payment' => 'Customer Payment',
                        'bank_deposit' => 'Bank Deposit',
                        'bank_withdrawal' => 'Bank Withdrawal',
                        'transfer_in' => 'Transfer In',
                        'transfer_out' => 'Transfer Out',
                        'vendor_payment' => 'Vendor Payment',
                        'manual' => 'Manual Adjustment',
                    ]),
                SelectFilter::make('direction')
                    ->options([
                        'credit' => 'Credit',
                        'debit' => 'Debit',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFinancialEntries::route('/'),
            'create' => CreateFinancialEntry::route('/create'),
            'edit' => EditFinancialEntry::route('/{record}/edit'),
        ];
    }
}
