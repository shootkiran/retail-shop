<?php

namespace App\Filament\Resources\Units;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Filament\Resources\Units\Pages\EditUnit;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Models\Unit;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class UnitResource extends Resource
{
    use RequiresBackOffice;

    protected static ?string $model = Unit::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedScale;

    protected static UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('symbol')
                ->maxLength(50),
            TextInput::make('multiplier_to_base')
                ->label('Multiplier to Base')
                ->numeric()
                ->default(1)
                ->required(),
            Toggle::make('is_base')
                ->default(false),
            Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('symbol')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('multiplier_to_base')
                    ->label('Multiplier')
                    ->sortable(),
                IconColumn::make('is_base')
                    ->boolean()
                    ->label('Base'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnits::route('/'),
            'create' => CreateUnit::route('/create'),
            'edit' => EditUnit::route('/{record}/edit'),
        ];
    }
}
