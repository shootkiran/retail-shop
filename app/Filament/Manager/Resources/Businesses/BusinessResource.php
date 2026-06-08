<?php

namespace App\Filament\Manager\Resources\Businesses;

use App\Filament\Manager\Resources\Businesses\Pages\CreateBusiness;
use App\Filament\Manager\Resources\Businesses\Pages\EditBusiness;
use App\Filament\Manager\Resources\Businesses\Pages\ListBusinesses;
use App\Models\Business;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static UnitEnum|string|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Business')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('legal_name')->maxLength(255),
                    TextInput::make('slug')->maxLength(255),
                    TextInput::make('country')->default(config('retail.country'))->required()->maxLength(255),
                    TextInput::make('timezone')->default(config('retail.timezone'))->required()->maxLength(255),
                    TextInput::make('currency_code')->default(config('retail.currency.code'))->required()->maxLength(3),
                    TextInput::make('currency_symbol')->default(config('retail.currency.symbol'))->required()->maxLength(10),
                    Toggle::make('is_active')->default(true),
                ]),
            Section::make('Contact')
                ->columns(2)
                ->schema([
                    TextInput::make('phone')->tel()->maxLength(255),
                    TextInput::make('email')->email()->maxLength(255),
                    TextInput::make('address')->columnSpanFull()->maxLength(500),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('country')->sortable(),
                TextColumn::make('timezone')->sortable(),
                TextColumn::make('currency_code')->label('Currency'),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinesses::route('/'),
            'create' => CreateBusiness::route('/create'),
            'edit' => EditBusiness::route('/{record}/edit'),
        ];
    }
}
