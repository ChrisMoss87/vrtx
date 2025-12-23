<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Modules;

use App\Domain\Modules\Events\ModuleRecordDeleted;
use App\Models\ModuleRecord;
use App\Services\Workflow\WorkflowTriggerService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Triggers workflows when a module record is deleted.
 */
class TriggerWorkflowOnDeletedListener
{
    public function __construct(
        private readonly WorkflowTriggerService $workflowTriggerService,
    ) {}

    public function handle(ModuleRecordDeleted $event): void
    {
        // For deleted records, we need to reconstruct a temporary model
        // since the record no longer exists in the database
        $record = new ModuleRecord();
        $record->id = $event->recordId();
        $record->module_id = $event->moduleId();
        $record->data = $event->data();

        try {
            $this->workflowTriggerService->onRecordDeleted($record);
        } catch (Throwable $e) {
            // Log the error but don't let workflow failures break record operations
            Log::error('Workflow trigger failed on record deleted', [
                'event' => 'deleted',
                'record_id' => $event->recordId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
