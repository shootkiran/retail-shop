<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class FilamentTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->actingAs($this->user);

        Filament::setCurrentPanel('admin');
        Filament::auth()->login($this->user);
        Auth::shouldUse(Filament::getCurrentPanel()->getAuthGuard());
    }
}
