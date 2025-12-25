<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Workflow\Entities\WorkflowExecution;
use App\Domain\Workflow\Repositories\WorkflowExecutionRepositoryInterface;
use App\Jobs\ExecuteWorkflowJob;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Execute scheduled (time-based) workflows.
 *
 * This command should be run every minute via the scheduler.
 * It finds workflows with time-based triggers that are due to run
 * and dispatches execution jobs for matching records.
 */
class ExecuteScheduledWorkflows extends Command
{
    protected $signature = 'workflows:execute-scheduled
                            {--dry-run : Show what would run without executing}
                            {--workflow= : Execute a specific workflow by ID}';

    protected $description = 'Execute scheduled (time-based) workflows that are due to run';

    public function __construct(
        private readonly WorkflowExecutionRepositoryInterface $executionRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $workflowId = $this->option('workflow');

        $this->info('Checking for scheduled workflows to execute...');

        $query = DB::table('workflows')
            ->active()
            ->where('trigger_type', Workflow::TRIGGER_TIME_BASED)
            ->whereNotNull('schedule_cron');

        if ($workflowId) {
            $query->where('id', $workflowId);
        }

        $workflows = $query->get();

        if ($workflows->isEmpty()) {
            $this->info('No scheduled workflows found.');
            return Command::SUCCESS;
        }

        $executed = 0;
        $skipped = 0;

        foreach ($workflows as $workflow) {
            $this->line("Checking workflow: {$workflow->name} (ID: {$workflow->id})");

            if (!$this->isDueToRun($workflow)) {
                $this->line("  → Not due to run yet (next: {$workflow->next_run_at})");
                $skipped++;
                continue;
            }

            if (!$workflow->canExecuteToday()) {
                $this->warn("  → Rate limit reached for today");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->info("  → [DRY RUN] Would execute workflow");
                $executed++;
                continue;
            }

            $count = $this->executeScheduledWorkflow($workflow);
            $executed++;

            $this->info("  → Triggered {$count} execution(s)");

            // Update next run time
            $this->updateNextRunTime($workflow);
        }

        $this->newLine();
        $this->info("Completed: {$executed} workflows processed, {$skipped} skipped");

        return Command::SUCCESS;
    }

    /**
     * Check if the workflow is due to run.
     */
    protected function isDueToRun(Workflow $workflow): bool
    {
        $now = now();

        // If next_run_at is set and is in the future, skip
        if ($workflow->next_run_at && $workflow->next_run_at->isFuture()) {
            return false;
        }

        // If next_run_at is null or in the past, check the cron expression
        try {
            $cron = new CronExpression($workflow->schedule_cron);
            return $cron->isDue($now);
        } catch (\Exception $e) {
            Log::warning('Invalid cron expression for workflow', [
                'workflow_id' => $workflow->id,
                'cron' => $workflow->schedule_cron,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Execute the scheduled workflow for matching records.
     */
    protected function executeScheduledWorkflow(Workflow $workflow): int
    {
        $config = $workflow->trigger_config ?? [];
        $executionCount = 0;

        // Get records to process based on trigger config
        $records = $this->getRecordsToProcess($workflow, $config);

        foreach ($records as $record) {
            // Check run-once-per-record constraint
            if ($workflow->run_once_per_record) {
                if ($workflow->hasRunForRecord($record->id, ModuleRecord::class, Workflow::TRIGGER_TIME_BASED)) {
                    continue;
                }
            }

            $this->dispatchForRecord($workflow, $record);
            $executionCount++;

            // Check if we've hit the daily limit
            if (!$workflow->canExecuteToday()) {
                Log::info('Workflow daily limit reached during scheduled execution', [
                    'workflow_id' => $workflow->id,
                    'executions_today' => $workflow->executions_today,
                ]);
                break;
            }
        }

        // If no specific records configured, run as a batch workflow (no record context)
        if ($records->isEmpty() && empty($config['filter_criteria'])) {
            $this->dispatchBatchWorkflow($workflow);
            $executionCount = 1;
        }

        return $executionCount;
    }

    /**
     * Get records to process based on workflow configuration.
     */
    protected function getRecordsToProcess(Workflow $workflow, array $config)
    {
        // Check if workflow is configured to run on all records or with specific criteria
        $query = DB::table('module_records')
            ->where('module_id', $workflow->module_id);

        // Apply date field criteria if configured
        if (!empty($config['date_field']) && !empty($config['date_offset'])) {
            $dateField = $config['date_field'];
            $offset = (int) $config['date_offset'];
            $offsetUnit = $config['date_offset_unit'] ?? 'days';

            $targetDate = match ($offsetUnit) {
                'hours' => now()->subHours($offset),
                'days' => now()->subDays($offset),
                'weeks' => now()->subWeeks($offset),
                'months' => now()->subMonths($offset),
                default => now()->subDays($offset),
            };

            // Use JSON path for the date field in the data column
            $query->whereRaw(
                "DATE(JSON_UNQUOTE(JSON_EXTRACT(data, ?))) = DATE(?)",
                ['$.' . $dateField, $targetDate->toDateString()]
            );
        }

        // Apply filter criteria if configured
        if (!empty($config['filter_criteria'])) {
            foreach ($config['filter_criteria'] as $filter) {
                $field = $filter['field'] ?? null;
                $operator = $filter['operator'] ?? '=';
                $value = $filter['value'] ?? null;

                if (!$field) {
                    continue;
                }

                $jsonPath = '$.' . $field;

                switch ($operator) {
                    case '=':
                    case 'equals':
                        $query->whereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(data, ?)) = ?",
                            [$jsonPath, $value]
                        );
                        break;
                    case '!=':
                    case 'not_equals':
                        $query->whereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(data, ?)) != ?",
                            [$jsonPath, $value]
                        );
                        break;
                    case 'contains':
                        $query->whereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(data, ?)) LIKE ?",
                            [$jsonPath, "%{$value}%"]
                        );
                        break;
                    case 'is_empty':
                        $query->whereRaw(
                            "(JSON_EXTRACT(data, ?) IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(data, ?)) = '')",
                            [$jsonPath, $jsonPath]
                        );
                        break;
                    case 'is_not_empty':
                        $query->whereRaw(
                            "JSON_EXTRACT(data, ?) IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(data, ?)) != ''",
                            [$jsonPath, $jsonPath]
                        );
                        break;
                }
            }
        }

        // Limit the number of records to process in one run
        $limit = $config['batch_size'] ?? 100;

        return $query->limit($limit)->get();
    }

    /**
     * Dispatch workflow execution for a specific record.
     */
    protected function dispatchForRecord(Workflow $workflow, ModuleRecord $record): void
    {
        $workflow->incrementTodayExecutions();

        $context = [
            'trigger_type' => Workflow::TRIGGER_TIME_BASED,
            'scheduled_at' => now()->toIso8601String(),
            'record' => [
                'id' => $record->id,
                'module_id' => $record->module_id,
                'data' => $record->data ?? [],
            ],
        ];

        $execution = WorkflowExecution::create(
            workflowId: $workflow->id,
            triggerType: Workflow::TRIGGER_TIME_BASED,
            triggerRecordId: $record->id,
            triggerRecordType: ModuleRecord::class,
            contextData: $context,
        );

        $savedExecution = $this->executionRepository->save($execution);

        if ($workflow->run_once_per_record) {
            $workflow->recordRunForRecord($record->id, ModuleRecord::class, Workflow::TRIGGER_TIME_BASED);
        }

        ExecuteWorkflowJob::dispatch($savedExecution->getId(), $context);

        Log::info('Scheduled workflow dispatched for record', [
            'workflow_id' => $workflow->id,
            'record_id' => $record->id,
            'execution_id' => $savedExecution->getId(),
        ]);
    }

    /**
     * Dispatch a batch workflow without specific record context.
     */
    protected function dispatchBatchWorkflow(Workflow $workflow): void
    {
        $workflow->incrementTodayExecutions();

        $context = [
            'trigger_type' => Workflow::TRIGGER_TIME_BASED,
            'scheduled_at' => now()->toIso8601String(),
            'batch_mode' => true,
        ];

        $execution = WorkflowExecution::create(
            workflowId: $workflow->id,
            triggerType: Workflow::TRIGGER_TIME_BASED,
            contextData: $context,
        );

        $savedExecution = $this->executionRepository->save($execution);

        ExecuteWorkflowJob::dispatch($savedExecution->getId(), $context);

        Log::info('Scheduled batch workflow dispatched', [
            'workflow_id' => $workflow->id,
            'execution_id' => $savedExecution->getId(),
        ]);
    }

    /**
     * Update the workflow's next run time based on cron expression.
     */
    protected function updateNextRunTime(Workflow $workflow): void
    {
        try {
            $cron = new CronExpression($workflow->schedule_cron);
            $nextRun = $cron->getNextRunDate();

            $workflow->update([
                'last_run_at' => now(),
                'next_run_at' => $nextRun,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update next run time for workflow', [
                'workflow_id' => $workflow->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
