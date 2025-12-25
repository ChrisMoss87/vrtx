<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Modules;

use App\Domain\Modules\Events\ModuleRecordUpdated;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
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
        private readonly ModuleRecordRepositoryInterface $moduleRecordRepository,
    ) {}

    public function handle(ModuleRecordUpdated $event): void
    {
        $record = $this->moduleRecordRepository->findById($event->moduleId(), $event->recordId());

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
