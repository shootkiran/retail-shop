<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\Reports;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class ReportsPageTest extends FilamentTestCase
{
    public function test_reports_page_can_render(): void
    {
        Livewire::test(Reports::class)->assertOk();
    }
}
