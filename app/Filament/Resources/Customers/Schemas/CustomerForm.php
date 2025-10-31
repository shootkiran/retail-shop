<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer Information')
                ->columns(2)
                ->icon('heroicon-m-identification')
                ->schema([
                    TextInput::make('name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('company')
                        ->maxLength(255),
                    Grid::make(2)
                        ->schema([
                            TextInput::make('email')
                                ->email()
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->tel()
                                ->maxLength(255),
                        ]),
                    Textarea::make('billing_address')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Account')
                ->columns(2)
                ->icon('heroicon-m-banknotes')
                ->schema([
                    TextInput::make('credit_limit')
                        ->numeric()
                        ->prefix('रू')
                        ->default(0)
                        ->minValue(0),
                    TextInput::make('outstanding_balance')
                        ->disabled()
                        ->numeric()
                        ->prefix('रू')
                        ->dehydrated(),
                ]),
        ]);
    }
}
