<?php

namespace App\Filament\Concerns;

trait RequiresBackOffice
{
    public static function canAccess(): bool
    {
        return auth()->user()?->canUseBackOffice() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
