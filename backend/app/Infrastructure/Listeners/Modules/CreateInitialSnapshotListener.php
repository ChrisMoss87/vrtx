<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Modules;

use App\Domain\Modules\Events\ModuleRecordCreated;
use App\Models\ModuleRecord;
use App\Services\TimeMachine\SnapshotService;

/**
 * Creates an initial snapshot when a module record is created.
 */
class CreateInitialSnapshotListener
{
    public function __construct(
        private readonly SnapshotService $snapshotService,
    ) {}

    public function handle(ModuleRecordCreated $event): void
    {
        $record = ModuleRecord::find($event->recordId());

        if ($record) {
            $this->snapshotService->createInitialSnapshot($record);
        }
    }
}
