<?php

use App\Jobs\CheckRottingDealsJob;
use App\Jobs\CreateForecastSnapshotsJob;
use App\Jobs\ProcessBlueprintSLAsJob;
use App\Jobs\ProcessCadenceStepsJob;
use App\Jobs\ProcessExpiredApprovalsJob;
use App\Jobs\ProcessScheduledEmailsJob;
use App\Jobs\SendRottingDigestJob;
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

// Execute scheduled workflows every minute
Schedule::command('workflows:execute-scheduled')->everyMinute()
    ->name('execute-scheduled-workflows')
    ->withoutOverlapping();

// Process scheduled emails every minute
Schedule::job(new ProcessScheduledEmailsJob)->everyMinute()
    ->name('process-scheduled-emails')
    ->withoutOverlapping();

// Process due cadence steps every minute
Schedule::job(new ProcessCadenceStepsJob)->everyMinute()
    ->name('process-cadence-steps')
    ->withoutOverlapping();

// Process Blueprint SLAs every minute (check for breaches and escalations)
Schedule::job(new ProcessBlueprintSLAsJob)->everyMinute()
    ->name('process-blueprint-slas')
    ->withoutOverlapping();

// Check for expired approval requests hourly
Schedule::job(new ProcessExpiredApprovalsJob)->hourly()
    ->name('process-expired-approvals')
    ->withoutOverlapping();

// Check for rotting deals hourly
Schedule::job(new CheckRottingDealsJob)->hourly()
    ->name('check-rotting-deals')
    ->withoutOverlapping();

// Send daily rotting deals digest at 8am
Schedule::job(new SendRottingDigestJob('daily'))->dailyAt('08:00')
    ->name('send-rotting-digest-daily')
    ->withoutOverlapping();

// Send weekly rotting deals digest on Monday at 8am
Schedule::job(new SendRottingDigestJob('weekly'))->weeklyOn(1, '08:00')
    ->name('send-rotting-digest-weekly')
    ->withoutOverlapping();

// Create daily forecast snapshots at midnight
Schedule::job(new CreateForecastSnapshotsJob('month'))->dailyAt('00:00')
    ->name('create-forecast-snapshots-daily')
    ->withoutOverlapping();

// Create weekly forecast snapshots on Sunday at midnight
Schedule::job(new CreateForecastSnapshotsJob('week'))->weeklyOn(0, '00:00')
    ->name('create-forecast-snapshots-weekly')
    ->withoutOverlapping();

// Create quarterly forecast snapshots on the 1st of each quarter at midnight
Schedule::job(new CreateForecastSnapshotsJob('quarter'))->quarterlyOn(1, '00:00')
    ->name('create-forecast-snapshots-quarterly')
    ->withoutOverlapping();
