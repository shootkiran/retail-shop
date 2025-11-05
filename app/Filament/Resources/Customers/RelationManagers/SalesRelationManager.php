<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'sales';

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Sale $record): string => SaleResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('reference')
                    ->label('Sale Ref.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('paymentMethod.name')
                    ->label('Payment Method')
                    ->toggleable(),
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'completed',
                        'danger' => 'cancelled',
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
                    ->money('ngn')
                    ->sortable(),
                TextColumn::make('amount_due')
                    ->label('Amount Due')
                    ->money('ngn')
                    ->badge()
                    ->color(fn (string | float | null $state): string => (float) $state > 0 ? 'danger' : 'success'),
                TextColumn::make('sold_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'pending' => 'Pending',
                        'partial' => 'Partial',
                        'paid' => 'Paid',
                    ]),
                Tables\Filters\TernaryFilter::make('amount_due')
                    ->label('Has Balance')
                    ->placeholder('All')
                    ->trueLabel('With balance')
                    ->falseLabel('Fully paid')
                    ->query(fn ($query, $state) => $query
                        ->when($state === 'true', fn ($q) => $q->where('amount_due', '>', 0))
                        ->when($state === 'false', fn ($q) => $q->where('amount_due', 0))),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (Sale $record): string => SaleResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

