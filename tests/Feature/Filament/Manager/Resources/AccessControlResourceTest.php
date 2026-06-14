<?php

namespace Tests\Feature\Filament\Manager\Resources;

use App\Filament\Manager\Resources\Permissions\Pages\ListPermissions;
use App\Filament\Manager\Resources\Roles\Pages\ListRoles;
use App\Filament\Manager\Resources\Users\Pages\ListUsers;
use App\Filament\Manager\Resources\Users\Pages\ViewUser;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\Filament\Manager\ManagerFilamentTestCase;

class AccessControlResourceTest extends ManagerFilamentTestCase
{
    public function test_users_list_page_can_render(): void
    {
        Livewire::test(ListUsers::class)->assertOk();
    }

    public function test_user_view_page_can_render(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->assertOk();
    }

    public function test_roles_list_page_can_render(): void
    {
        Livewire::test(ListRoles::class)->assertOk();
    }

    public function test_permissions_list_page_can_render(): void
    {
        Livewire::test(ListPermissions::class)->assertOk();
    }

    public function test_impersonation_route_is_registered(): void
    {
        $target = User::factory()->create([
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $url = route('impersonate', ['id' => $target->getKey()]);

        $this->assertStringContainsString('/impersonate/', $url);
    }
}
