<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Services\ChartOfAccountsService;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ChartOfAccountsService::class);

        Business::query()
            ->orderBy('id')
            ->get()
            ->each(fn (Business $business) => $service->seedBusiness($business));
    }
}
