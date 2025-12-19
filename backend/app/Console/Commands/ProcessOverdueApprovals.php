<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Blueprint\ApprovalEscalationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Process overdue approval requests.
 *
 * This command should be run hourly via the scheduler.
 * It handles reminders, escalations, and auto-rejections for
 * pending approval requests based on their configured policies.
 */
class ProcessOverdueApprovals extends Command
{
    protected $signature = 'approvals:process-overdue
                            {--dry-run : Show what would happen without executing}';

    protected $description = 'Process overdue approval requests (reminders, escalations, auto-rejections)';

    public function __construct(
        private readonly ApprovalEscalationService $escalationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Processing overdue approval requests...');

        if ($dryRun) {
            $this->warn('Running in dry-run mode - no changes will be made');
            // In dry-run mode, we'd need a different approach
            // For now, just show what the service would do
            $this->line('Would process all pending approval requests for:');
            $this->line('  - Sending reminders based on reminder_hours config');
            $this->line('  - Escalating based on escalation_hours config');
            $this->line('  - Auto-rejecting based on auto_reject_days config');
            return Command::SUCCESS;
        }

        try {
            $summary = $this->escalationService->processOverdueApprovals();

            $this->newLine();
            $this->info('Processing complete:');
            $this->table(
                ['Action', 'Count'],
                [
                    ['Reminders Sent', $summary['reminders_sent']],
                    ['Escalations Processed', $summary['escalations_processed']],
                    ['Auto-Rejections', $summary['auto_rejections']],
                    ['Errors', count($summary['errors'])],
                ]
            );

            if (!empty($summary['errors'])) {
                $this->newLine();
                $this->error('Errors occurred during processing:');
                foreach ($summary['errors'] as $error) {
                    $this->line("  - Request #{$error['request_id']}: {$error['error']}");
                }
            }

            Log::info('Overdue approvals processed', $summary);

            return count($summary['errors']) > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to process overdue approvals: ' . $e->getMessage());
            Log::error('Failed to process overdue approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
