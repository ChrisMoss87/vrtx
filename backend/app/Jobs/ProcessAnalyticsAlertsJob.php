<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Analytics\AnalyticsAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAnalyticsAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function handle(AnalyticsAlertService $alertService): void
    {
        Log::info('Processing analytics alerts');

        $results = $alertService->processAlerts();

        Log::info('Analytics alerts processed', $results);

        // Dispatch notifications for triggered alerts
        if ($results['triggered'] > 0) {
            SendAlertNotificationsJob::dispatch();
        }
    }

    public function tags(): array
    {
        return ['analytics', 'alerts'];
    }
}
