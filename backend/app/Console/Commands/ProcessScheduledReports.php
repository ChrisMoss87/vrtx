<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendScheduledReportJob;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledReports extends Command
{
    protected $signature = 'reports:process-scheduled {--dry-run : Show what would be processed without actually sending}';

    protected $description = 'Process and send scheduled reports';

    public function handle(): int
    {
        $this->info('Processing scheduled reports...');

        $dryRun = $this->option('dry-run');
        $now = Carbon::now();
        $processedCount = 0;

        // Get all reports with enabled schedules
        $reports = Report::whereNotNull('schedule')
            ->whereRaw("(schedule->>'enabled')::boolean = true")
            ->get();

        $this->info("Found {$reports->count()} reports with schedules.");

        foreach ($reports as $report) {
            $schedule = $report->schedule;

            if (!$schedule || !($schedule['enabled'] ?? false)) {
                continue;
            }

            $shouldRun = $this->shouldRunReport($report, $schedule, $now);

            if ($shouldRun) {
                if ($dryRun) {
                    $this->line("  [DRY RUN] Would send: {$report->name} ({$report->id})");
                } else {
                    $this->dispatchReport($report);
                    $this->line("  Dispatched: {$report->name} ({$report->id})");
                }
                $processedCount++;
            }
        }

        $this->info("Processed {$processedCount} reports.");

        return self::SUCCESS;
    }

    protected function shouldRunReport(Report $report, array $schedule, Carbon $now): bool
    {
        $frequency = $schedule['frequency'] ?? 'daily';
        $time = $schedule['time'] ?? '09:00';
        $lastRun = $report->last_run_at ? Carbon::parse($report->last_run_at) : null;

        // Parse scheduled time
        [$hour, $minute] = explode(':', $time);
        $scheduledTime = $now->copy()->setTime((int) $hour, (int) $minute, 0);

        // Check if we're past the scheduled time today
        if ($now->lt($scheduledTime)) {
            return false;
        }

        // Check based on frequency
        switch ($frequency) {
            case 'hourly':
                // Run if at least 1 hour since last run
                if ($lastRun && $lastRun->diffInHours($now) < 1) {
                    return false;
                }
                return true;

            case 'daily':
                // Run once per day at scheduled time
                if ($lastRun && $lastRun->isSameDay($now)) {
                    return false;
                }
                return true;

            case 'weekly':
                $dayOfWeek = $schedule['day_of_week'] ?? 1; // Monday default
                if ($now->dayOfWeek !== $dayOfWeek) {
                    return false;
                }
                if ($lastRun && $lastRun->isSameWeek($now)) {
                    return false;
                }
                return true;

            case 'monthly':
                $dayOfMonth = $schedule['day_of_month'] ?? 1;
                if ($now->day !== $dayOfMonth) {
                    return false;
                }
                if ($lastRun && $lastRun->isSameMonth($now)) {
                    return false;
                }
                return true;

            default:
                return false;
        }
    }

    protected function dispatchReport(Report $report): void
    {
        try {
            SendScheduledReportJob::dispatch($report->id);

            Log::info('Scheduled report dispatched', [
                'report_id' => $report->id,
                'report_name' => $report->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch scheduled report', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);

            $this->error("  Failed to dispatch: {$report->name} - {$e->getMessage()}");
        }
    }
}
