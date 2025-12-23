<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Modules;

use App\Domain\Modules\Events\ModuleRecordUpdated;
use App\Models\ModuleRecord;
use App\Services\TimeMachine\SnapshotService;

/**
 * Creates a change snapshot when a module record is updated.
 */
class CreateChangeSnapshotListener
{
    public function __construct(
        private readonly SnapshotService $snapshotService,
    ) {}

    public function handle(ModuleRecordUpdated $event): void
    {
        $record = ModuleRecord::find($event->recordId());

        if ($record) {
            $this->snapshotService->createChangeSnapshot(
                $record,
                $event->oldData(),
                $event->newData(),
            );
        }
    }
}
