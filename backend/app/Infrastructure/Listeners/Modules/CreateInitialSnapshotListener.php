<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Modules;

use App\Domain\Modules\Events\ModuleRecordCreated;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Services\TimeMachine\SnapshotService;

/**
 * Creates an initial snapshot when a module record is created.
 */
class CreateInitialSnapshotListener
{
    public function __construct(
        private readonly SnapshotService $snapshotService,
        private readonly ModuleRecordRepositoryInterface $moduleRecordRepository,
    ) {}

    public function handle(ModuleRecordCreated $event): void
    {
        $record = $this->moduleRecordRepository->findById($event->moduleId(), $event->recordId());

        if ($record) {
            $this->snapshotService->createInitialSnapshot($record);
        }
    }
}
