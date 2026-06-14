<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ManagerPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ManagerPanelProvider::class,
];
