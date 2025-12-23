<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Modules;

use App\Domain\Modules\Events\ModuleRecordUpdated;
use App\Models\ModuleRecord;
use App\Services\Workflow\WorkflowTriggerService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Triggers workflows when a module record is updated.
 */
class TriggerWorkflowOnUpdatedListener
{
    public function __construct(
        private readonly WorkflowTriggerService $workflowTriggerService,
    ) {}

    public function handle(ModuleRecordUpdated $event): void
    {
        $record = ModuleRecord::find($event->recordId());

        if (!$record) {
            return;
        }

        try {
            $this->workflowTriggerService->onRecordUpdated($record, $event->oldData());
        } catch (Throwable $e) {
            // Log the error but don't let workflow failures break record operations
            Log::error('Workflow trigger failed on record updated', [
                'event' => 'updated',
                'record_id' => $event->recordId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
