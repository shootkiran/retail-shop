<?php

namespace App\Filament\Resources\CashRegisters;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Resources\CashRegisters\Pages\CreateCashRegister;
use App\Filament\Resources\CashRegisters\Pages\EditCashRegister;
use App\Filament\Resources\CashRegisters\Pages\ListCashRegisters;
use App\Filament\Resources\CashRegisters\Pages\ViewCashRegister;
use App\Filament\Resources\CashRegisters\Schemas\CashRegisterInfolist;
use App\Models\CashRegister;
use App\Models\PosTerminal;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CashRegisterResource extends Resource
{
    use RequiresBackOffice;

    protected static ?string $model = CashRegister::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->maxLength(255),
            Select::make('pos_terminal_id')
                ->label('POS Terminal')
                ->options(fn (): array => PosTerminal::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload(),
            TextInput::make('opening_balance')
                ->numeric()
                ->prefix('रू')
                ->default(0),
            Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CashRegisterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (CashRegister $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('terminal.name')
                    ->label('Terminal')
                    ->toggleable(),
                TextColumn::make('opening_balance')
                    ->label('Opening')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('current_balance')
                    ->label('Current Balance')
                    ->money('NPR')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashRegisters::route('/'),
            'view' => ViewCashRegister::route('/{record}'),
            'create' => CreateCashRegister::route('/create'),
            'edit' => EditCashRegister::route('/{record}/edit'),
        ];
    }
}
