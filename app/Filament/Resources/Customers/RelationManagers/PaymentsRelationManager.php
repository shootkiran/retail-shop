<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('payment_date')
                ->required()
                ->default(now()),
            Select::make('method')
                ->options([
                    'cash' => 'Cash',
                    'bank' => 'Bank Transfer',
                    'cheque' => 'Cheque',
                    'online' => 'Online Transfer',
                ])
                ->required(),
            TextInput::make('amount')
                ->numeric()
                ->prefix('रू')
                ->minValue(0.01)
                ->required(),
            Select::make('bank_account_id')
                ->label('Bank Account')
                ->relationship('bankAccount', 'name')
                ->searchable()
                ->preload(),
            Select::make('cash_register_id')
                ->label('Cash Register')
                ->relationship('cashRegister', 'name')
                ->searchable()
                ->preload(),
            TextInput::make('reference')
                ->maxLength(255),
            TextInput::make('notes')
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('amount')->money('NPR')->sortable(),
                TextColumn::make('reference')->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => array_merge($data, [
                        'customer_id' => $this->ownerRecord->getKey(),
                    ])),
            ])
            ->actions([
                DeleteAction::make(),
            ]);
    }
}
