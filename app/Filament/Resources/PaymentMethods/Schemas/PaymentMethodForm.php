<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use App\Models\BankAccount;
use App\Models\CashRegister;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment Method')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('type')
                        ->placeholder('Cash, Card, Transfer...')
                        ->maxLength(255),
                    Select::make('settlement_account_type')
                        ->label('Settlement Account Type')
                        ->options([
                            'bank' => 'Bank Account',
                            'cash_register' => 'Cash Register',
                        ])
                        ->live(),
                    Select::make('settlement_account_id')
                        ->label('Settlement Account')
                        ->options(fn (Get $get): array => match ($get('settlement_account_type')) {
                            'bank' => BankAccount::query()->orderBy('name')->pluck('name', 'id')->all(),
                            'cash_register' => CashRegister::query()->orderBy('name')->pluck('name', 'id')->all(),
                            default => [],
                        })
                        ->searchable()
                        ->preload(),
                    Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),
        ]);
    }
}
