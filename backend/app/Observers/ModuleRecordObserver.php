<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ModuleRecord;
use App\Services\TimeMachine\SnapshotService;

class ModuleRecordObserver
{
    public function __construct(
        protected SnapshotService $snapshotService
    ) {}

    /**
     * Handle the ModuleRecord "created" event.
     */
    public function created(ModuleRecord $record): void
    {
        $this->snapshotService->createInitialSnapshot($record);
    }

    /**
     * Handle the ModuleRecord "updating" event.
     * We capture old data before the update happens.
     */
    public function updating(ModuleRecord $record): void
    {
        // Store the original data for comparison after update
        $record->_oldData = $record->getOriginal('data');
    }

    /**
     * Handle the ModuleRecord "updated" event.
     */
    public function updated(ModuleRecord $record): void
    {
        // Compare old and new data to create a snapshot
        $oldData = $record->_oldData ?? [];
        $newData = $record->data ?? [];

        if (!empty($oldData) || !empty($newData)) {
            $this->snapshotService->createChangeSnapshot($record, $oldData, $newData);
        }
    }
}
