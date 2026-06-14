<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use App\Models\PaymentMethod;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentMethodInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Method Details')
                ->columnSpanFull()
                ->icon('heroicon-m-banknotes')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('name')->label('Name'),
                            TextEntry::make('type')->label('Type')->badge(),
                            TextEntry::make('is_active')
                                ->label('Status')
                                ->badge()
                                ->state(fn (PaymentMethod $record): string => $record->is_active ? 'Active' : 'Inactive')
                                ->color(fn (PaymentMethod $record): string => $record->is_active ? 'success' : 'gray'),
                            TextEntry::make('description')->label('Description')->columnSpanFull()->placeholder('No description provided'),
                        ]),
                ]),
            Section::make('Settlement')
                ->columnSpanFull()
                ->icon('heroicon-m-building-library')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('settlement_account_type')
                                ->label('Settlement account type')
                                ->badge()
                                ->state(fn (PaymentMethod $record): string => $record->settlement_account_type ?: 'Not set'),
                            TextEntry::make('settlement_account_name')
                                ->label('Settlement account')
                                ->state(function (PaymentMethod $record): string {
                                    $record->loadMissing('settlementAccount');

                                    return $record->settlementAccount?->name ?? 'Not set';
                                }),
                            TextEntry::make('usage_count')
                                ->label('Usage')
                                ->state(fn (PaymentMethod $record): int => $record->sales()->count() + $record->purchases()->count()),
                        ]),
                ]),
        ]);
    }
}
