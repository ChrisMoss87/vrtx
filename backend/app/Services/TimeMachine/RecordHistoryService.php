<?php

declare(strict_types=1);

namespace App\Services\TimeMachine;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecordHistoryService
{
    /**
     * Get the record state at a specific point in time.
     */
    public function getRecordAtTimestamp(int $moduleId, int $recordId, string $timestamp): ?array
    {
        // First, try to find a snapshot at or before this timestamp
        $snapshot = RecordSnapshot::getAtTimestamp($moduleId, $recordId, $timestamp);

        if ($snapshot) {
            // If snapshot is after the requested time, we need to replay changes
            // Otherwise, we can use the snapshot directly
            return $this->buildStateFromSnapshot($snapshot, $timestamp);
        }

        // No snapshot found, need to get current state and work backwards
        $record = DB::table('module_records')->where('module_id', $moduleId)
            ->where('id', $recordId)
            ->first();

        if (!$record) {
            return null;
        }

        // If record was created after the timestamp, return null
        if ($record->created_at > $timestamp) {
            return null;
        }

        return $this->reconstructStateAtTime($moduleId, $recordId, $timestamp, $record->data);
    }

    /**
     * Get the history of changes for a record.
     */
    public function getRecordHistory(
        int $moduleId,
        int $recordId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $limit = 100
    ): array {
        $query = RecordSnapshot::forRecord($moduleId, $recordId)
            ->inDateRange($startDate, $endDate)
            ->with('creator:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(function ($snapshot) {
            return [
                'id' => $snapshot->id,
                'timestamp' => $snapshot->created_at->toIso8601String(),
                'type' => $snapshot->snapshot_type,
                'changes' => $snapshot->change_summary,
                'created_by' => $snapshot->creator ? [
                    'id' => $snapshot->creator->id,
                    'name' => $snapshot->creator->name,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * Get detailed field change log for a record.
     */
    public function getFieldChanges(
        int $moduleId,
        int $recordId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $fieldApiName = null
    ): array {
        $query = FieldChangeLog::forRecord($moduleId, $recordId)
            ->inDateRange($startDate, $endDate)
            ->with('changedByUser:id,name,email')
            ->orderBy('changed_at', 'desc');

        if ($fieldApiName) {
            $query->forField($fieldApiName);
        }

        return $query->get()->map(function ($change) {
            return [
                'id' => $change->id,
                'field' => $change->field_api_name,
                'old_value' => $change->old_value,
                'new_value' => $change->new_value,
                'changed_at' => $change->changed_at->toIso8601String(),
                'changed_by' => $change->changedByUser ? [
                    'id' => $change->changedByUser->id,
                    'name' => $change->changedByUser->name,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * Get a timeline of events for visualization.
     */
    public function getTimeline(int $moduleId, int $recordId): array
    {
        $events = [];

        // Get snapshots as timeline events
        $snapshots = RecordSnapshot::forRecord($moduleId, $recordId)
            ->with('creator:id,name')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($snapshots as $snapshot) {
            $events[] = [
                'timestamp' => $snapshot->created_at->toIso8601String(),
                'type' => $snapshot->snapshot_type,
                'label' => $this->getEventLabel($snapshot),
                'details' => $snapshot->change_summary,
                'user' => $snapshot->creator?->name,
            ];
        }

        return $events;
    }

    /**
     * Get timeline markers for the time machine slider.
     */
    public function getTimelineMarkers(
        int $moduleId,
        int $recordId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $query = RecordSnapshot::forRecord($moduleId, $recordId)
            ->inDateRange($startDate, $endDate)
            ->select(['id', 'created_at', 'snapshot_type', 'change_summary'])
            ->orderBy('created_at', 'asc');

        return $query->get()->map(function ($snapshot) {
            return [
                'id' => $snapshot->id,
                'timestamp' => $snapshot->created_at->toIso8601String(),
                'type' => $snapshot->snapshot_type,
                'label' => $this->getMarkerLabel($snapshot),
            ];
        })->toArray();
    }

    /**
     * Build state from a snapshot, applying any changes after it up to the timestamp.
     */
    protected function buildStateFromSnapshot(RecordSnapshot $snapshot, string $timestamp): array
    {
        // If snapshot is at or after the timestamp, return snapshot data
        if ($snapshot->created_at->toIso8601String() >= $timestamp) {
            return $snapshot->snapshot_data;
        }

        // Get changes between snapshot and timestamp
        $changes = FieldChangeLog::forRecord($snapshot->module_id, $snapshot->record_id)
            ->where('changed_at', '>', $snapshot->created_at)
            ->where('changed_at', '<=', $timestamp)
            ->orderBy('changed_at', 'asc')
            ->get();

        // Apply changes to snapshot data
        $state = $snapshot->snapshot_data;
        foreach ($changes as $change) {
            $state[$change->field_api_name] = $change->new_value;
        }

        return $state;
    }

    /**
     * Reconstruct state at a specific time by reversing changes.
     */
    protected function reconstructStateAtTime(
        int $moduleId,
        int $recordId,
        string $timestamp,
        array $currentData
    ): array {
        // Get all changes after the timestamp (in reverse order)
        $changes = FieldChangeLog::forRecord($moduleId, $recordId)
            ->where('changed_at', '>', $timestamp)
            ->orderBy('changed_at', 'desc')
            ->get();

        // Reverse each change to get back to the previous state
        $state = $currentData;
        foreach ($changes as $change) {
            $state[$change->field_api_name] = $change->old_value;
        }

        return $state;
    }

    /**
     * Get a human-readable label for a snapshot event.
     */
    protected function getEventLabel(RecordSnapshot $snapshot): string
    {
        $summary = $snapshot->change_summary ?? [];

        return match ($snapshot->snapshot_type) {
            RecordSnapshot::TYPE_FIELD_CHANGE => $this->formatFieldChangeLabel($summary),
            RecordSnapshot::TYPE_STAGE_CHANGE => 'Stage changed',
            RecordSnapshot::TYPE_DAILY => 'Daily snapshot',
            RecordSnapshot::TYPE_MANUAL => $summary['note'] ?? 'Manual snapshot',
            default => 'Unknown event',
        };
    }

    /**
     * Get a short label for timeline markers.
     */
    protected function getMarkerLabel(RecordSnapshot $snapshot): string
    {
        $summary = $snapshot->change_summary ?? [];

        return match ($snapshot->snapshot_type) {
            RecordSnapshot::TYPE_FIELD_CHANGE => $this->getFieldChangeMarkerLabel($summary),
            RecordSnapshot::TYPE_STAGE_CHANGE => 'Stage',
            RecordSnapshot::TYPE_DAILY => 'Snapshot',
            RecordSnapshot::TYPE_MANUAL => 'Manual',
            default => 'Event',
        };
    }

    /**
     * Format field change label.
     */
    protected function formatFieldChangeLabel(array $summary): string
    {
        if (isset($summary['action']) && $summary['action'] === 'created') {
            return 'Record created';
        }

        $fieldsChanged = $summary['fields_changed'] ?? [];
        $count = count($fieldsChanged);

        if ($count === 0) {
            return 'Fields updated';
        }

        if ($count === 1) {
            return ucfirst(str_replace('_', ' ', $fieldsChanged[0])) . ' changed';
        }

        return "{$count} fields changed";
    }

    /**
     * Get short marker label for field changes.
     */
    protected function getFieldChangeMarkerLabel(array $summary): string
    {
        if (isset($summary['action']) && $summary['action'] === 'created') {
            return 'Created';
        }

        $fieldsChanged = $summary['fields_changed'] ?? [];

        if (count($fieldsChanged) === 1) {
            return ucfirst(str_replace('_', ' ', $fieldsChanged[0]));
        }

        return 'Updated';
    }
}
