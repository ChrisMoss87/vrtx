<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ModuleRecord;
use App\Services\TimeMachine\SnapshotService;
use App\Services\Workflow\WorkflowTriggerService;
use Illuminate\Support\Facades\Log;

class ModuleRecordObserver
{
    /**
     * Store old data temporarily during updates.
     * Using a static array keyed by record ID to avoid setting dynamic properties
     * on the model which would be treated as database columns.
     */
    protected static array $pendingOldData = [];

    public function __construct(
        protected SnapshotService $snapshotService,
        protected WorkflowTriggerService $workflowTriggerService,
    ) {}

    /**
     * Handle the ModuleRecord "created" event.
     */
    public function created(ModuleRecord $record): void
    {
        $this->snapshotService->createInitialSnapshot($record);

        // Trigger workflows for record creation
        $this->triggerWorkflowsSafely(
            fn() => $this->workflowTriggerService->onRecordCreated($record),
            'created',
            $record->id
        );
    }

    /**
     * Handle the ModuleRecord "updating" event.
     * We capture old data before the update happens.
     */
    public function updating(ModuleRecord $record): void
    {
        // Store the original data for comparison after update
        // Use a static array to avoid setting dynamic properties on the model
        self::$pendingOldData[$record->id] = $record->getOriginal('data');
    }

    /**
     * Handle the ModuleRecord "updated" event.
     */
    public function updated(ModuleRecord $record): void
    {
        // Compare old and new data to create a snapshot
        $oldData = self::$pendingOldData[$record->id] ?? [];
        $newData = $record->data ?? [];

        // Clean up the stored old data
        unset(self::$pendingOldData[$record->id]);

        if (!empty($oldData) || !empty($newData)) {
            $this->snapshotService->createChangeSnapshot($record, $oldData, $newData);
        }

        // Trigger workflows for record update
        $this->triggerWorkflowsSafely(
            fn() => $this->workflowTriggerService->onRecordUpdated($record, $oldData),
            'updated',
            $record->id
        );
    }

    /**
     * Handle the ModuleRecord "deleted" event.
     */
    public function deleted(ModuleRecord $record): void
    {
        // Trigger workflows for record deletion
        $this->triggerWorkflowsSafely(
            fn() => $this->workflowTriggerService->onRecordDeleted($record),
            'deleted',
            $record->id
        );
    }

    /**
     * Safely trigger workflows, catching any exceptions to prevent
     * workflow errors from breaking record operations.
     */
    protected function triggerWorkflowsSafely(callable $trigger, string $event, int $recordId): void
    {
        try {
            $trigger();
        } catch (\Throwable $e) {
            // Log the error but don't let workflow failures break record operations
            Log::error('Workflow trigger failed', [
                'event' => $event,
                'record_id' => $recordId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
