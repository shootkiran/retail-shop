<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer Profile')
                ->columnSpanFull()
                ->icon('heroicon-m-user-circle')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('name')
                                ->label('Full name'),
                            TextEntry::make('company')
                                ->label('Company')
                                ->placeholder('No company linked'),
                            TextEntry::make('email')
                                ->label('Email')
                                ->placeholder('No email provided'),
                            TextEntry::make('phone')
                                ->label('Phone')
                                ->placeholder('No phone provided'),
                            TextEntry::make('billing_address')
                                ->label('Billing address')
                                ->columnSpanFull()
                                ->placeholder('No billing address provided'),
                        ]),
                ]),
            Section::make('Account Summary')
                ->columnSpanFull()
                ->icon('heroicon-m-banknotes')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('credit_limit')
                                ->label('Credit limit')
                                ->money('NPR')
                                ->state(fn (Customer $record): float => (float) $record->credit_limit),
                            TextEntry::make('outstanding_balance')
                                ->label('Outstanding balance')
                                ->money('NPR')
                                ->state(fn (Customer $record): float => (float) $record->outstanding_balance)
                                ->color(fn (Customer $record): string => (float) $record->outstanding_balance > 0 ? 'danger' : 'success'),
                            TextEntry::make('available_credit')
                                ->label('Available credit')
                                ->money('NPR')
                                ->state(fn (Customer $record): float => max((float) $record->credit_limit - (float) $record->outstanding_balance, 0))
                                ->color(fn (Customer $record): string => (float) $record->outstanding_balance > (float) $record->credit_limit ? 'warning' : 'success'),
                            TextEntry::make('credit_status')
                                ->label('Credit status')
                                ->badge()
                                ->state(fn (Customer $record): string => match (true) {
                                    (float) $record->credit_limit <= 0 => 'No credit limit',
                                    (float) $record->outstanding_balance > (float) $record->credit_limit => 'Over limit',
                                    (float) $record->outstanding_balance > 0 => 'Open balance',
                                    default => 'Clear',
                                })
                                ->color(fn (Customer $record): string => match (true) {
                                    (float) $record->credit_limit <= 0 => 'gray',
                                    (float) $record->outstanding_balance > (float) $record->credit_limit => 'danger',
                                    (float) $record->outstanding_balance > 0 => 'warning',
                                    default => 'success',
                                }),
                        ]),
                ]),
            Section::make('Activity')
                ->columnSpanFull()
                ->icon('heroicon-m-chart-bar-square')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('sales_count')
                                ->label('Sales')
                                ->state(fn (Customer $record): int => $record->sales()->count()),
                            TextEntry::make('payments_count')
                                ->label('Payments')
                                ->state(fn (Customer $record): int => $record->payments()->count()),
                            TextEntry::make('last_sale_at')
                                ->label('Last sale')
                                ->state(fn (Customer $record) => $record->sales()->latest('sold_at')->value('sold_at'))
                                ->dateTime()
                                ->placeholder('No sales yet'),
                            TextEntry::make('last_payment_at')
                                ->label('Last payment')
                                ->state(fn (Customer $record) => $record->payments()->latest('payment_date')->value('payment_date'))
                                ->date()
                                ->placeholder('No payments yet'),
                        ]),
                ]),
        ]);
    }
}
