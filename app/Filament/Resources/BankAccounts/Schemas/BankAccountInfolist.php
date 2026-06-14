<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use App\Models\BankAccount;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankAccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account Overview')
                ->columnSpanFull()
                ->icon('heroicon-m-building-library')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('name')->label('Account name'),
                            TextEntry::make('bank_name')->label('Bank'),
                            TextEntry::make('account_number')->label('Account number')->placeholder('Not set'),
                            TextEntry::make('account_type')->label('Account type')->badge(),
                            TextEntry::make('is_active')
                                ->label('Status')
                                ->badge()
                                ->state(fn (BankAccount $record): string => $record->is_active ? 'Active' : 'Inactive')
                                ->color(fn (BankAccount $record): string => $record->is_active ? 'success' : 'gray'),
                        ]),
                ]),
            Section::make('Balances')
                ->columnSpanFull()
                ->icon('heroicon-m-banknotes')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('opening_balance')->label('Opening balance')->money('NPR')->state(fn (BankAccount $record): float => (float) $record->opening_balance),
                            TextEntry::make('current_balance')->label('Current balance')->money('NPR')->state(fn (BankAccount $record): float => $record->current_balance)->color(fn (BankAccount $record): string => $record->current_balance >= 0 ? 'success' : 'danger'),
                            TextEntry::make('entry_count')->label('Transactions')->state(fn (BankAccount $record): int => $record->entries()->count()),
                        ]),
                ]),
            Section::make('Ledger Summary')
                ->columnSpanFull()
                ->icon('heroicon-m-arrow-trending-up')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('credits')->label('Total credits')->money('NPR')->state(fn (BankAccount $record): float => (float) $record->entries()->where('direction', 'credit')->sum('amount')),
                            TextEntry::make('debits')->label('Total debits')->money('NPR')->state(fn (BankAccount $record): float => (float) $record->entries()->where('direction', 'debit')->sum('amount')),
                            TextEntry::make('latest_entry')
                                ->label('Latest transaction')
                                ->state(fn (BankAccount $record) => $record->entries()->latest('entry_date')->value('entry_date'))
                                ->date()
                                ->placeholder('No transactions yet'),
                            TextEntry::make('payments_count')->label('Customer payments')->state(fn (BankAccount $record): int => $record->payments()->count()),
                        ]),
                ]),
        ]);
    }
}
