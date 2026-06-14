<?php

namespace App\Filament\Resources\CashRegisters\Schemas;

use App\Models\CashRegister;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CashRegisterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Register Overview')
                ->columnSpanFull()
                ->icon('heroicon-m-calculator')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('name')->label('Register name'),
                            TextEntry::make('code')->label('Code')->badge(),
                            TextEntry::make('terminal.name')->label('POS terminal')->placeholder('Unassigned'),
                            TextEntry::make('is_active')
                                ->label('Status')
                                ->badge()
                                ->state(fn (CashRegister $record): string => $record->is_active ? 'Active' : 'Inactive')
                                ->color(fn (CashRegister $record): string => $record->is_active ? 'success' : 'gray'),
                        ]),
                ]),
            Section::make('Balances')
                ->columnSpanFull()
                ->icon('heroicon-m-banknotes')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('opening_balance')->label('Opening balance')->money('NPR')->state(fn (CashRegister $record): float => (float) $record->opening_balance),
                            TextEntry::make('current_balance')->label('Current balance')->money('NPR')->state(fn (CashRegister $record): float => $record->current_balance)->color(fn (CashRegister $record): string => $record->current_balance >= 0 ? 'success' : 'danger'),
                            TextEntry::make('entry_count')->label('Transactions')->state(fn (CashRegister $record): int => $record->entries()->count()),
                        ]),
                ]),
            Section::make('Ledger Summary')
                ->columnSpanFull()
                ->icon('heroicon-m-arrow-trending-up')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('credits')->label('Total credits')->money('NPR')->state(fn (CashRegister $record): float => (float) $record->entries()->where('direction', 'credit')->sum('amount')),
                            TextEntry::make('debits')->label('Total debits')->money('NPR')->state(fn (CashRegister $record): float => (float) $record->entries()->where('direction', 'debit')->sum('amount')),
                            TextEntry::make('latest_entry')
                                ->label('Latest transaction')
                                ->state(fn (CashRegister $record) => $record->entries()->latest('entry_date')->value('entry_date'))
                                ->date()
                                ->placeholder('No transactions yet'),
                        ]),
                ]),
        ]);
    }
}
