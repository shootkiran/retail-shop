<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\CustomerPayment;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record]))
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
                    ->visible(fn (?Customer $record) => filled($record?->company)),
                TextColumn::make('outstanding_balance')
                    ->label('Balance')
                    ->money('NPR')
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
                Action::make('statement')
                    ->label('Statement')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->modalHeading(fn (Customer $record): string => 'Generate Statement for '.$record->name)
                    ->modalSubmitActionLabel('Download PDF')
                    ->form([
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->default(now()->startOfMonth())
                            ->required(),
                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->default(now()->endOfMonth())
                            ->required(),
                    ])
                    ->action(function (Customer $record, array $data) {
                        return redirect()->route('customers.statement', [
                            'customer' => $record,
                            'startDate' => $data['startDate'],
                            'endDate' => $data['endDate'],
                        ]);
                    }),
                Action::make('receivePayment')
                    ->label('Receive Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalHeading(fn (Customer $record): string => 'Receive payment from '.$record->name)
                    ->modalSubmitActionLabel('Post Payment')
                    ->form([
                        DatePicker::make('payment_date')
                            ->label('Payment Date')
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
                        Select::make('account_type')
                            ->label('Received Into')
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
                    ->action(function (Customer $record, array $data): void {
                        $payment = CustomerPayment::create([
                            'customer_id' => $record->getKey(),
                            'payment_date' => $data['payment_date'],
                            'method' => $data['method'],
                            'amount' => $data['amount'],
                            'reference' => $data['reference'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'bank_account_id' => $data['account_type'] === 'bank' ? (int) $data['account_id'] : null,
                            'cash_register_id' => $data['account_type'] === 'cash_register' ? (int) $data['account_id'] : null,
                        ]);

                        Notification::make()
                            ->title('Customer payment received')
                            ->body('Payment reference: '.$payment->reference)
                            ->success()
                            ->send();
                    }),
                ViewAction::make()
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record])),
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
