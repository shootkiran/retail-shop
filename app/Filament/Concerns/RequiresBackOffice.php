<?php

namespace App\Filament\Concerns;

trait RequiresBackOffice
{
    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->canUseBackOffice() ?? false;
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return static::canAccess();
    }
}
