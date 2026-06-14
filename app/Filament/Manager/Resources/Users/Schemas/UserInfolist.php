<?php

namespace App\Filament\Manager\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('User Profile')
                ->columnSpanFull()
                ->icon('heroicon-m-user')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('name')->label('Name'),
                            TextEntry::make('email')->label('Email'),
                            TextEntry::make('currentBusiness.name')->label('Business')->placeholder('Unassigned'),
                            TextEntry::make('office_type')->label('Office type')->badge(),
                            TextEntry::make('is_active')
                                ->label('Status')
                                ->badge()
                                ->state(fn (User $record): string => $record->is_active ? 'Active' : 'Inactive')
                                ->color(fn (User $record): string => $record->is_active ? 'success' : 'gray'),
                            TextEntry::make('is_platform_admin')
                                ->label('Platform admin')
                                ->badge()
                                ->state(fn (User $record): string => $record->is_platform_admin ? 'Yes' : 'No')
                                ->color(fn (User $record): string => $record->is_platform_admin ? 'success' : 'gray'),
                        ]),
                ]),
            Section::make('Roles')
                ->columnSpanFull()
                ->icon('heroicon-m-shield-check')
                ->schema([
                    RepeatableEntry::make('roles')
                        ->schema([
                            TextEntry::make('name')->badge(),
                        ])
                        ->state(fn (User $record): array => $record->roles->map(fn ($role): array => [
                            'name' => $role->name,
                        ])->all()),
                ]),
            Section::make('Access Summary')
                ->columnSpanFull()
                ->icon('heroicon-m-building-office-2')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('businesses_count')
                                ->label('Businesses')
                                ->state(fn (User $record): int => $record->businesses()->count()),
                            TextEntry::make('email_verified_at')
                                ->label('Email verified')
                                ->dateTime()
                                ->placeholder('Not verified'),
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),
                        ]),
                ]),
        ]);
    }
}
