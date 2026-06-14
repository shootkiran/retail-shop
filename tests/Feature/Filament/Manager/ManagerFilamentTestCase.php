<?php

namespace Tests\Feature\Filament\Manager;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

abstract class ManagerFilamentTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'is_platform_admin' => true,
            'office_type' => 'back_office',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        Filament::setCurrentPanel('manager');
        Filament::auth()->login($this->user);
        Auth::shouldUse(Filament::getCurrentPanel()->getAuthGuard());
    }
}
