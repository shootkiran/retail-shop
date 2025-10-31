<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->toggleable(),
                BadgeColumn::make('company')
                    ->colors(['primary'])
                    ->visible(fn ($record) => filled($record->company)),
                TextColumn::make('outstanding_balance')
                    ->label('Balance')
                    ->money('ngn')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('company')
                    ->options(fn () => Customer::query()
                        ->whereNotNull('company')
                        ->where('company', '!=', '')
                        ->distinct()
                        ->orderBy('company')
                        ->pluck('company', 'company')
                        ->toArray()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
