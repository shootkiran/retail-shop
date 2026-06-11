<?php

namespace App\Filament\Resources\BankAccounts;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Resources\BankAccounts\Pages\CreateBankAccount;
use App\Filament\Resources\BankAccounts\Pages\EditBankAccount;
use App\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Models\BankAccount;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class BankAccountResource extends Resource
{
    use RequiresBackOffice;

    protected static ?string $model = BankAccount::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('bank_name')
                ->label('Bank Name')
                ->required()
                ->maxLength(255),
            TextInput::make('account_number')
                ->maxLength(255),
            Select::make('account_type')
                ->options([
                    'checking' => 'Checking',
                    'savings' => 'Savings',
                    'current' => 'Current',
                    'other' => 'Other',
                ])
                ->required()
                ->default('checking'),
            TextInput::make('opening_balance')
                ->numeric()
                ->prefix('रू')
                ->default(0),
            Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bank_name')
                    ->label('Bank')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_number')
                    ->label('Account No.')
                    ->toggleable(),
                TextColumn::make('account_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('opening_balance')
                    ->label('Opening')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('current_balance')
                    ->label('Current Balance')
                    ->money('NPR')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankAccounts::route('/'),
            'create' => CreateBankAccount::route('/create'),
            'edit' => EditBankAccount::route('/{record}/edit'),
        ];
    }
}
