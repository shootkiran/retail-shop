<?php

namespace App\Filament\Manager\Resources\Permissions\Pages;

use App\Filament\Manager\Resources\Permissions\PermissionResource;
use Filament\Resources\Pages\EditRecord;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;
}
