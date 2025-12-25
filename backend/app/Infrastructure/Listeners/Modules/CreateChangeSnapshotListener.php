<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Modules;

use App\Domain\Modules\Events\ModuleRecordUpdated;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Services\TimeMachine\SnapshotService;

/**
 * Creates a change snapshot when a module record is updated.
 */
class CreateChangeSnapshotListener
{
    public function __construct(
        private readonly SnapshotService $snapshotService,
        private readonly ModuleRecordRepositoryInterface $moduleRecordRepository,
    ) {}

    public function handle(ModuleRecordUpdated $event): void
    {
        $record = $this->moduleRecordRepository->findById($event->moduleId(), $event->recordId());

        if ($record) {
            $this->snapshotService->createChangeSnapshot(
                $record,
                $event->oldData(),
                $event->newData(),
            );
        }
    }
}
