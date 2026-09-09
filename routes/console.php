<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\MlmSettingsService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:reset {--email=admin@mlm.com} {--password=Admin@123}', function () {
    $this->call(\App\Console\Commands\ResetAdminPassword::class, [
        '--email' => $this->option('email'),
        '--password' => $this->option('password'),
    ]);
})->purpose('Reset or create admin user');

// Monthly deductions: 5% Tier (to direct sponsor).
// The run day follows the admin-managed `income_cycle_start_day` setting, so the
// process always runs right after the current cycle ends (day 1 = 1st, day 5 =
// 5th, ...). Because schedule:run executes every minute, changing the setting is
// picked up automatically — no manual cron edit needed.
$cycleDay = (int) (new MlmSettingsService)->getSetting('income_cycle_start_day', 1);
if ($cycleDay < 1 || $cycleDay > 28) {
    $cycleDay = 1;
}

Schedule::command('mlm:process-monthly-deductions')
    ->monthlyOn($cycleDay, '00:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/monthly-deductions.log'));
