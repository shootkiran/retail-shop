<?php

namespace App\Filament\Manager\Resources\Users\Pages;

use App\Filament\Manager\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function afterSave(): void
    {
        $this->syncBusinessMembership($this->record);
    }

    protected function syncBusinessMembership(User $user): void
    {
        if (! $user->current_business_id) {
            return;
        }

        $user->businesses()->syncWithoutDetaching([
            $user->current_business_id => [
                'role' => $user->is_platform_admin ? 'admin' : 'staff',
                'office_type' => $user->office_type,
                'is_active' => $user->is_active,
            ],
        ]);
    }
}
