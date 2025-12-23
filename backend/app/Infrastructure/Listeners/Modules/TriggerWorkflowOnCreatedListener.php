<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Modules;

use App\Domain\Modules\Events\ModuleRecordCreated;
use App\Models\ModuleRecord;
use App\Services\Workflow\WorkflowTriggerService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Triggers workflows when a module record is created.
 */
class TriggerWorkflowOnCreatedListener
{
    public function __construct(
        private readonly WorkflowTriggerService $workflowTriggerService,
    ) {}

    public function handle(ModuleRecordCreated $event): void
    {
        $record = ModuleRecord::find($event->recordId());

        if (!$record) {
            return;
        }

        try {
            $this->workflowTriggerService->onRecordCreated($record);
        } catch (Throwable $e) {
            // Log the error but don't let workflow failures break record operations
            Log::error('Workflow trigger failed on record created', [
                'event' => 'created',
                'record_id' => $event->recordId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
