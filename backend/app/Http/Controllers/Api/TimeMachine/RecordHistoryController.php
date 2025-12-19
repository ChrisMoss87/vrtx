<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\TimeMachine;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleRecord;
use App\Services\TimeMachine\DiffService;
use App\Services\TimeMachine\RecordHistoryService;
use App\Services\TimeMachine\SnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordHistoryController extends Controller
{
    public function __construct(
        protected RecordHistoryService $historyService,
        protected SnapshotService $snapshotService,
        protected DiffService $diffService
    ) {}

    /**
     * Get history for a record.
     * GET /api/v1/records/{moduleApiName}/{recordId}/history
     */
    public function history(Request $request, string $moduleApiName, int $recordId): JsonResponse
    {
        $module = Module::where('api_name', $moduleApiName)->firstOrFail();

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $history = $this->historyService->getRecordHistory(
            $module->id,
            $recordId,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $validated['limit'] ?? 100
        );

        return response()->json([
            'data' => $history,
            'meta' => [
                'module' => $moduleApiName,
                'record_id' => $recordId,
            ],
        ]);
    }

    /**
     * Get record state at a specific timestamp.
     * GET /api/v1/records/{moduleApiName}/{recordId}/at/{timestamp}
     */
    public function atTimestamp(string $moduleApiName, int $recordId, string $timestamp): JsonResponse
    {
        $module = Module::where('api_name', $moduleApiName)->firstOrFail();

        // Validate and parse timestamp
        try {
            $parsedTimestamp = \Carbon\Carbon::parse($timestamp)->toIso8601String();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid timestamp format',
            ], 422);
        }

        $state = $this->historyService->getRecordAtTimestamp($module->id, $recordId, $parsedTimestamp);

        if ($state === null) {
            return response()->json([
                'message' => 'Record did not exist at the specified timestamp',
            ], 404);
        }

        // Get module fields for context
        $fields = $module->fields()->get()->keyBy('api_name')->map(fn($f) => [
            'label' => $f->label,
            'type' => $f->type,
        ]);

        return response()->json([
            'data' => $state,
            'meta' => [
                'module' => $moduleApiName,
                'record_id' => $recordId,
                'timestamp' => $parsedTimestamp,
                'fields' => $fields,
            ],
        ]);
    }

    /**
     * Get diff between two timestamps.
     * GET /api/v1/records/{moduleApiName}/{recordId}/diff
     */
    public function diff(Request $request, string $moduleApiName, int $recordId): JsonResponse
    {
        $module = Module::where('api_name', $moduleApiName)->firstOrFail();

        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        try {
            $fromTimestamp = \Carbon\Carbon::parse($validated['from'])->toIso8601String();
            $toTimestamp = \Carbon\Carbon::parse($validated['to'])->toIso8601String();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid timestamp format',
            ], 422);
        }

        $diff = $this->diffService->getDiff($module->id, $recordId, $fromTimestamp, $toTimestamp);

        if (isset($diff['error'])) {
            return response()->json([
                'message' => $diff['error'],
            ], 404);
        }

        return response()->json([
            'data' => $diff,
        ]);
    }

    /**
     * Get side-by-side comparison.
     * GET /api/v1/records/{moduleApiName}/{recordId}/compare
     */
    public function compare(Request $request, string $moduleApiName, int $recordId): JsonResponse
    {
        $module = Module::where('api_name', $moduleApiName)->firstOrFail();

        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        try {
            $fromTimestamp = \Carbon\Carbon::parse($validated['from'])->toIso8601String();
            $toTimestamp = \Carbon\Carbon::parse($validated['to'])->toIso8601String();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid timestamp format',
            ], 422);
        }

        $comparison = $this->diffService->getSideBySideComparison(
            $module->id,
            $recordId,
            $fromTimestamp,
            $toTimestamp
        );

        if (isset($comparison['error'])) {
            return response()->json([
                'message' => $comparison['error'],
            ], 404);
        }

        return response()->json([
            'data' => $comparison,
        ]);
    }

    /**
     * Get timeline events for visualization.
     * GET /api/v1/records/{moduleApiName}/{recordId}/timeline
     */
    public function timeline(string $moduleApiName, int $recordId): JsonResponse
    {
        $module = Module::where('api_name', $moduleApiName)->firstOrFail();

        $timeline = $this->historyService->getTimeline($module->id, $recordId);

        return response()->json([
            'data' => $timeline,
            'meta' => [
                'module' => $moduleApiName,
                'record_id' => $recordId,
            ],
        ]);
    }

    /**
     * Get timeline markers for time machine slider.
     * GET /api/v1/records/{moduleApiName}/{recordId}/timeline-markers
     */
    public function timelineMarkers(Request $request, string $moduleApiName, int $recordId): JsonResponse
    {
        $module = Module::where('api_name', $moduleApiName)->firstOrFail();

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $markers = $this->historyService->getTimelineMarkers(
            $module->id,
            $recordId,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json([
            'data' => $markers,
        ]);
    }

    /**
     * Get field changes for a record.
     * GET /api/v1/records/{moduleApiName}/{recordId}/field-changes
     */
    public function fieldChanges(Request $request, string $moduleApiName, int $recordId): JsonResponse
    {
        $module = Module::where('api_name', $moduleApiName)->firstOrFail();

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'field' => 'nullable|string|max:100',
        ]);

        $changes = $this->historyService->getFieldChanges(
            $module->id,
            $recordId,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $validated['field'] ?? null
        );

        return response()->json([
            'data' => $changes,
        ]);
    }

    /**
     * Create a manual snapshot.
     * POST /api/v1/records/{moduleApiName}/{recordId}/snapshot
     */
    public function createSnapshot(Request $request, string $moduleApiName, int $recordId): JsonResponse
    {
        $module = Module::where('api_name', $moduleApiName)->firstOrFail();

        $record = ModuleRecord::where('module_id', $module->id)
            ->where('id', $recordId)
            ->firstOrFail();

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $snapshot = $this->snapshotService->createManualSnapshot($record, $validated['note'] ?? null);

        return response()->json([
            'data' => [
                'id' => $snapshot->id,
                'timestamp' => $snapshot->created_at->toIso8601String(),
                'type' => $snapshot->snapshot_type,
            ],
            'message' => 'Snapshot created successfully',
        ], 201);
    }
}
