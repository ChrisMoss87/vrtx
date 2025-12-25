<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Modules;

use App\Domain\Modules\Entities\ModuleRecord;
use App\Domain\Modules\Events\ModuleRecordDeleted;
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
        // For deleted records, we need to reconstruct the entity
        // since the record no longer exists in the database
        $record = ModuleRecord::reconstitute(
            id: $event->recordId(),
            moduleId: $event->moduleId(),
            data: $event->data(),
            createdBy: null,
            updatedBy: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: null,
        );

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
