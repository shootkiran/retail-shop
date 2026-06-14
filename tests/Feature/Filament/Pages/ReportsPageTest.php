<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\Reports;
use App\Filament\Widgets\Reports\CustomerDuesAgingChart;
use App\Filament\Widgets\Reports\TopCustomersChart;
use App\Filament\Widgets\Reports\TopItemsChart;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class ReportsPageTest extends FilamentTestCase
{
    public function test_reports_page_can_render(): void
    {
        $page = app(Reports::class);
        $method = new \ReflectionMethod($page, 'getHeaderWidgets');
        $method->setAccessible(true);

        $this->assertSame([
            TopItemsChart::class,
            TopCustomersChart::class,
            CustomerDuesAgingChart::class,
        ], $method->invoke($page));

        Livewire::test(Reports::class)
            ->assertOk()
            ->assertSee('Reports focuses on higher-level analysis');
    }
}
