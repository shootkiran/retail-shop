<?php

namespace App\Filament\Resources\Vendors\Tables;

use App\Filament\Resources\Vendors\VendorResource;
use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Models\FinancialEntry;
use App\Models\Vendor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Vendor $record): string => VendorResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_person')
                    ->label('Contact Person')
                    ->toggleable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->toggleable(),
                BadgeColumn::make('address')
                    ->label('Address')
                    ->limit(30)
                    ->visible(fn (?Vendor $record) => filled($record?->address)),
            ])
            ->filters([
                Filter::make('has_contact')
                    ->label('Has contact person')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('contact_person')->where('contact_person', '!=', '')),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('makePayment')
                    ->label('Make Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalHeading(fn (Vendor $record): string => 'Make payment to '.$record->name)
                    ->modalSubmitActionLabel('Post Payment')
                    ->form([
                        DatePicker::make('entry_date')
                            ->label('Payment Date')
                            ->required()
                            ->default(now()),
                        Select::make('account_type')
                            ->label('Paid From')
                            ->options([
                                'bank' => 'Bank Account',
                                'cash_register' => 'Cash Register',
                            ])
                            ->live()
                            ->required(),
                        Select::make('account_id')
                            ->label('Account')
                            ->options(fn (Get $get): array => match ($get('account_type')) {
                                'bank' => BankAccount::query()->orderBy('name')->pluck('name', 'id')->all(),
                                'cash_register' => CashRegister::query()->orderBy('name')->pluck('name', 'id')->all(),
                                default => [],
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('amount')
                            ->numeric()
                            ->prefix('रू')
                            ->minValue(0.01)
                            ->required(),
                        TextInput::make('reference')
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->action(function (Vendor $record, array $data): void {
                        $accountClass = $data['account_type'] === 'cash_register'
                            ? CashRegister::class
                            : BankAccount::class;

                        FinancialEntry::create([
                            'accountable_type' => $accountClass,
                            'accountable_id' => (int) $data['account_id'],
                            'entry_type' => 'vendor_payment',
                            'direction' => 'debit',
                            'amount' => $data['amount'],
                            'entry_date' => $data['entry_date'],
                            'reference' => $data['reference'] ?? null,
                            'notes' => trim(($record->name.' vendor payment')."\n".($data['notes'] ?? '')),
                        ]);

                        Notification::make()
                            ->title('Vendor payment posted')
                            ->success()
                            ->send();
                    }),
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
