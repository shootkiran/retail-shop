<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vendor Details')
                ->columns(2)
                ->icon('heroicon-m-building-storefront')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('contact_person')
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
                    Textarea::make('address')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
