<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Modules\Events\ModuleRecordCreated;
use App\Domain\Modules\Events\ModuleRecordDeleted;
use App\Domain\Modules\Events\ModuleRecordUpdated;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Models\ModuleRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Observer for ModuleRecord model.
 *
 * This observer acts as a bridge between Eloquent model events and domain events.
 * It dispatches domain events which are handled by dedicated listeners for:
 * - Creating snapshots (TimeMachine)
 * - Triggering workflows
 */
class ModuleRecordObserver
{
    /**
     * Store old data temporarily during updates.
     * Using a static array keyed by record ID to avoid setting dynamic properties
     * on the model which would be treated as database columns.
     */
    protected static array $pendingOldData = [];

    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Handle the ModuleRecord "created" event.
     */
    public function created(ModuleRecord $record): void
    {
        $this->eventDispatcher->dispatch(new ModuleRecordCreated(
            recordId: $record->id,
            moduleId: $record->module_id,
            data: $record->data ?? [],
            createdBy: Auth::id(),
        ));
    }

    /**
     * Handle the ModuleRecord "updating" event.
     * We capture old data before the update happens.
     */
    public function updating(ModuleRecord $record): void
    {
        // Store the original data for comparison after update
        // Use a static array to avoid setting dynamic properties on the model
        self::$pendingOldData[$record->id] = $record->getOriginal('data') ?? [];
    }

    /**
     * Handle the ModuleRecord "updated" event.
     */
    public function updated(ModuleRecord $record): void
    {
        // Compare old and new data
        $oldData = self::$pendingOldData[$record->id] ?? [];
        $newData = $record->data ?? [];

        // Clean up the stored old data
        unset(self::$pendingOldData[$record->id]);

        $this->eventDispatcher->dispatch(new ModuleRecordUpdated(
            recordId: $record->id,
            moduleId: $record->module_id,
            oldData: $oldData,
            newData: $newData,
            updatedBy: Auth::id(),
        ));
    }

    /**
     * Handle the ModuleRecord "deleted" event.
     */
    public function deleted(ModuleRecord $record): void
    {
        $this->eventDispatcher->dispatch(new ModuleRecordDeleted(
            recordId: $record->id,
            moduleId: $record->module_id,
            data: $record->data ?? [],
            deletedBy: Auth::id(),
        ));
    }
}
