<?php

declare(strict_types=1);

namespace App\Services\TimeMachine;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SnapshotService
{
    /**
     * Create a snapshot when a record is created.
     */
    public function createInitialSnapshot(ModuleRecord $record): RecordSnapshot
    {
        return DB::table('record_snapshots')->insertGetId([
            'module_id' => $record->module_id,
            'record_id' => $record->id,
            'snapshot_data' => $record->data,
            'snapshot_type' => RecordSnapshot::TYPE_FIELD_CHANGE,
            'change_summary' => ['action' => 'created'],
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Create a snapshot when fields are changed.
     */
    public function createChangeSnapshot(
        ModuleRecord $record,
        array $oldData,
        array $newData
    ): ?RecordSnapshot {
        $changes = $this->calculateChanges($oldData, $newData);

        if (empty($changes)) {
            return null;
        }

        // Log individual field changes
        foreach ($changes as $fieldApiName => $change) {
            DB::table('field_change_logs')->insertGetId([
                'module_id' => $record->module_id,
                'record_id' => $record->id,
                'field_api_name' => $fieldApiName,
                'old_value' => $change['old'],
                'new_value' => $change['new'],
                'changed_by' => Auth::id(),
                'changed_at' => now(),
            ]);
        }

        // Determine snapshot type based on what changed
        $snapshotType = RecordSnapshot::TYPE_FIELD_CHANGE;
        if (isset($changes['stage']) || isset($changes['stage_id'])) {
            $snapshotType = RecordSnapshot::TYPE_STAGE_CHANGE;
        }

        return DB::table('record_snapshots')->insertGetId([
            'module_id' => $record->module_id,
            'record_id' => $record->id,
            'snapshot_data' => $newData,
            'snapshot_type' => $snapshotType,
            'change_summary' => [
                'fields_changed' => array_keys($changes),
                'changes' => $changes,
            ],
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Create a manual snapshot (user-initiated).
     */
    public function createManualSnapshot(ModuleRecord $record, ?string $note = null): RecordSnapshot
    {
        return DB::table('record_snapshots')->insertGetId([
            'module_id' => $record->module_id,
            'record_id' => $record->id,
            'snapshot_data' => $record->data,
            'snapshot_type' => RecordSnapshot::TYPE_MANUAL,
            'change_summary' => [
                'action' => 'manual_snapshot',
                'note' => $note,
            ],
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Create daily snapshots for active records.
     * This is called by a scheduled job.
     */
    public function createDailySnapshots(Module $module): int
    {
        $count = 0;

        // Get records that had activity in the last 30 days
        $records = DB::table('module_records')->where('module_id', $module->id)
            ->where('updated_at', '>=', now()->subDays(30))
            ->cursor();

        foreach ($records as $record) {
            // Check if we already have a daily snapshot today
            $existingToday = RecordSnapshot::forRecord($module->id, $record->id)
                ->ofType(RecordSnapshot::TYPE_DAILY)
                ->whereDate('created_at', today())
                ->exists();

            if (!$existingToday) {
                DB::table('record_snapshots')->insertGetId([
                    'module_id' => $module->id,
                    'record_id' => $record->id,
                    'snapshot_data' => $record->data,
                    'snapshot_type' => RecordSnapshot::TYPE_DAILY,
                    'change_summary' => ['action' => 'daily_snapshot'],
                    'created_by' => null,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Calculate the differences between two data states.
     */
    protected function calculateChanges(array $oldData, array $newData): array
    {
        $changes = [];

        // Check for changed and new fields
        foreach ($newData as $key => $newValue) {
            $oldValue = $oldData[$key] ?? null;

            if ($this->hasChanged($oldValue, $newValue)) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        // Check for removed fields
        foreach ($oldData as $key => $oldValue) {
            if (!array_key_exists($key, $newData) && $oldValue !== null) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => null,
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if a value has changed.
     */
    protected function hasChanged($oldValue, $newValue): bool
    {
        // Handle null comparisons
        if ($oldValue === null && $newValue === null) {
            return false;
        }
        if ($oldValue === null || $newValue === null) {
            return true;
        }

        // Handle array comparisons
        if (is_array($oldValue) && is_array($newValue)) {
            return json_encode($oldValue) !== json_encode($newValue);
        }

        // Handle numeric comparisons (avoid float issues)
        if (is_numeric($oldValue) && is_numeric($newValue)) {
            return (float) $oldValue !== (float) $newValue;
        }

        return $oldValue !== $newValue;
    }

    /**
     * Clean up old snapshots based on retention policy.
     */
    public function cleanupOldSnapshots(int $retentionDays = 730): int
    {
        $cutoffDate = now()->subDays($retentionDays);

        // Delete old daily snapshots (keep field/stage changes longer)
        return DB::table('record_snapshots')->where('created_at', '<', $cutoffDate)
            ->where('snapshot_type', RecordSnapshot::TYPE_DAILY)
            ->delete();
    }
}
