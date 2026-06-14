<?php

namespace App\Filament\Manager\Resources\Permissions\Pages;

use App\Filament\Manager\Resources\Permissions\PermissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;
}
