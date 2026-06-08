<?php

namespace App\Filament\Resources\BusinessSettings;

use App\Filament\Concerns\RequiresBackOffice;
use App\Filament\Resources\BusinessSettings\Pages\EditBusinessSetting;
use App\Filament\Resources\BusinessSettings\Pages\ListBusinessSettings;
use App\Models\BusinessSetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class BusinessSettingResource extends Resource
{
    use RequiresBackOffice;

    protected static ?string $model = BusinessSetting::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Business Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Localisation')
                ->columns(3)
                ->schema([
                    TextInput::make('country')->required()->default(config('retail.country'))->maxLength(255),
                    TextInput::make('timezone')->required()->default(config('retail.timezone'))->maxLength(255),
                    TextInput::make('currency_code')->required()->default(config('retail.currency.code'))->maxLength(3),
                    TextInput::make('currency_symbol')->required()->default(config('retail.currency.symbol'))->maxLength(10),
                    TextInput::make('currency_decimal_places')->numeric()->minValue(0)->maxValue(4)->default(2),
                    TextInput::make('date_format')->required()->default('d M Y')->maxLength(50),
                    TextInput::make('time_format')->required()->default('H:i')->maxLength(50),
                ]),
            Section::make('Invoices')
                ->columns(2)
                ->schema([
                    TextInput::make('invoice_prefix')->required()->default('SL')->maxLength(20),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business.name')->label('Business')->sortable(),
                TextColumn::make('country')->sortable(),
                TextColumn::make('timezone')->sortable(),
                TextColumn::make('currency_code')->label('Currency'),
                TextColumn::make('currency_symbol')->label('Symbol'),
                TextColumn::make('invoice_prefix')->label('Invoice prefix'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessSettings::route('/'),
            'edit' => EditBusinessSetting::route('/{record}/edit'),
        ];
    }
}
