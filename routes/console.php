<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:reset {--email=admin@mlm.com} {--password=Admin@123}', function () {
    $this->call(\App\Console\Commands\ResetAdminPassword::class, [
        '--email' => $this->option('email'),
        '--password' => $this->option('password'),
    ]);
})->purpose('Reset or create admin user');
