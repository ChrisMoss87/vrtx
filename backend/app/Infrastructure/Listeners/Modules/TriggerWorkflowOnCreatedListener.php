<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Modules;

use App\Domain\Modules\Events\ModuleRecordCreated;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
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
        private readonly ModuleRecordRepositoryInterface $moduleRecordRepository,
    ) {}

    public function handle(ModuleRecordCreated $event): void
    {
        $record = $this->moduleRecordRepository->findById($event->moduleId(), $event->recordId());

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
