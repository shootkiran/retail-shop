<?php

namespace App\Filament\Manager\Resources\Users\Pages;

use App\Filament\Manager\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('impersonate')
                ->label('Impersonate')
                ->icon('heroicon-o-arrow-right-end-on-rectangle')
                ->color('warning')
                ->url(fn (): ?string => route('impersonate', ['id' => $this->record->getKey()]))
                ->visible(fn (): bool => auth()->user()?->canImpersonate() === true && $this->record->canBeImpersonated()),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
