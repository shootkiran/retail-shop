<?php

namespace App\Filament\Resources\Purchases\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Purchase Ref.')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->sortable(),
                TextColumn::make('paymentMethod.name')
                    ->label('Payment Method')
                    ->toggleable(),
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'draft',
                        'info' => 'ordered',
                        'success' => 'received',
                        'danger' => 'cancelled',
                    ]),
                TextColumn::make('grand_total')
                    ->label('Grand Total')
                    ->money('ngn')
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('Amount Paid')
                    ->money('ngn')
                    ->sortable(),
                TextColumn::make('amount_due')
                    ->label('Amount Due')
                    ->money('ngn')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                TextColumn::make('purchased_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'ordered' => 'Ordered',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    ]),
                TernaryFilter::make('amount_due')
                    ->label('Has Balance')
                    ->placeholder('All')
                    ->trueLabel('Outstanding')
                    ->falseLabel('Settled')
                    ->query(fn ($query, $state) => $query->when($state === 'true', fn ($q) => $q->where('amount_due', '>', 0))
                        ->when($state === 'false', fn ($q) => $q->where('amount_due', 0))),
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
