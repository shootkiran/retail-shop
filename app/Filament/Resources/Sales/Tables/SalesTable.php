<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record) => SaleResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('reference')
                    ->label('Sale Ref.')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->toggleable()
                    ->sortable()
                    ->url(fn ($record) => $record->customer
                        ? CustomerResource::getUrl('view', ['record' => $record->customer])
                        : null),
                TextColumn::make('paymentMethod.name')
                    ->label('Payment Method')
                    ->toggleable(),
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->icons([
                        'heroicon-o-pencil-square' => 'draft',
                        'heroicon-o-check-circle' => 'completed',
                        'heroicon-o-x-mark' => 'cancelled',
                    ]),
                BadgeColumn::make('payment_status')
                    ->label('Payment Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'partial',
                        'success' => 'paid',
                    ]),
                TextColumn::make('grand_total')
                    ->label('Grand Total')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('Amount Paid')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('amount_due')
                    ->label('Amount Due')
                    ->money('NPR')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('sold_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'pending' => 'Pending',
                        'partial' => 'Partial',
                        'paid' => 'Paid',
                    ]),
                TernaryFilter::make('amount_due')
                    ->label('Has Balance')
                    ->placeholder('All')
                    ->trueLabel('With balance')
                    ->falseLabel('Fully paid')
                    ->query(fn ($query, $state) => $query->when($state === 'true', fn ($q) => $q->where('amount_due', '>', 0))
                        ->when($state === 'false', fn ($q) => $q->where('amount_due', 0))),
            ])
            ->recordActions([
                ViewAction::make(),
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
