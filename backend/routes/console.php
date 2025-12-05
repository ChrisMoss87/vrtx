<?php

use App\Jobs\ProcessScheduledEmailsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Jobs
|--------------------------------------------------------------------------
*/

// Process scheduled emails every minute
Schedule::job(new ProcessScheduledEmailsJob)->everyMinute()
    ->name('process-scheduled-emails')
    ->withoutOverlapping();
