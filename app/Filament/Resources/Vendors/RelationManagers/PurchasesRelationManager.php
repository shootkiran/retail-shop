<?php

namespace App\Filament\Resources\Vendors\RelationManagers;

use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchasesRelationManager extends RelationManager
{
    protected static string $relationship = 'purchases';

    protected static ?string $recordTitleAttribute = 'reference';

    public function form(Schema $schema): Schema
    {
        return PurchaseForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('paymentMethod'))
            ->columns([
                TextColumn::make('reference')
                    ->label('Purchase Ref.')
                    ->searchable()
                    ->copyable()
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
                    ->query(
                        fn (Builder $query, $state): Builder => $query
                            ->when($state === 'true', fn (Builder $innerQuery): Builder => $innerQuery->where('amount_due', '>', 0))
                            ->when($state === 'false', fn (Builder $innerQuery): Builder => $innerQuery->where('amount_due', 0)),
                    ),
            ])
            ->headerActions([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

