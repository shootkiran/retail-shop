<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    public function createApplication()
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        if (file_exists(__DIR__ . '/../.env.example')) {
            $app->loadEnvironmentFrom('.env.example');
        }

        $app->make(Kernel::class)->bootstrap();

        if (blank(config('app.key'))) {
            config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        }

        return $app;
    }
}
