<?php

namespace App\Filament\Resources\BankAccounts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $recordTitleAttribute = 'reference';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_date')
                    ->date()
                    ->sortable(),
                BadgeColumn::make('entry_type')
                    ->colors([
                        'primary' => 'sale_receipt',
                        'success' => 'customer_payment',
                        'info' => 'bank_deposit',
                        'warning' => 'bank_withdrawal',
                        'gray' => 'manual',
                    ])
                    ->label('Type'),
                BadgeColumn::make('direction')
                    ->colors([
                        'success' => 'credit',
                        'danger' => 'debit',
                    ]),
                TextColumn::make('amount')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('reference')
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('notes')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
