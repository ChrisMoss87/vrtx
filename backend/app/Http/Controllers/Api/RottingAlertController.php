<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModuleRecord;
use App\Models\Pipeline;
use App\Models\RottingAlert;
use App\Models\RottingAlertSetting;
use App\Models\Stage;
use App\Services\Rotting\DealRottingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RottingAlertController extends Controller
{
    public function __construct(
        protected DealRottingService $rottingService
    ) {}

    /**
     * Get rotting deals for current user or specified pipeline.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pipeline_id' => 'nullable|integer|exists:pipelines,id',
            'status' => 'nullable|string|in:warming,stale,rotting',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $userId = Auth::id();
        $pipelineId = $validated['pipeline_id'] ?? null;

        $rottingDeals = $this->rottingService->getRottingDealsForUser($userId, $pipelineId);

        // Filter by status if specified
        if (isset($validated['status'])) {
            $rottingDeals = $rottingDeals->filter(
                fn ($item) => $item['rot_status']['status'] === $validated['status']
            );
        }

        // Transform for response
        $data = $rottingDeals->map(fn ($item) => [
            'record' => [
                'id' => $item['record']->id,
                'module_id' => $item['record']->module_id,
                'data' => $item['record']->data,
                'created_by' => $item['record']->created_by,
                'last_activity_at' => $item['record']->last_activity_at?->toIso8601String(),
            ],
            'stage' => [
                'id' => $item['stage']->id,
                'name' => $item['stage']->name,
                'color' => $item['stage']->color,
                'rotting_days' => $item['stage']->rotting_days,
            ],
            'pipeline' => [
                'id' => $item['pipeline']->id,
                'name' => $item['pipeline']->name,
            ],
            'rot_status' => $item['rot_status'],
        ])->values();

        // Simple pagination
        $perPage = $validated['per_page'] ?? 25;
        $page = $request->input('page', 1);
        $total = $data->count();
        $items = $data->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => (int) $page,
                'last_page' => (int) ceil($total / $perPage),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Get rot status for a specific record.
     */
    public function show(int $recordId): JsonResponse
    {
        $record = ModuleRecord::with('module')->findOrFail($recordId);

        // Find pipeline and stage for this record
        $pipeline = Pipeline::where('module_id', $record->module_id)->first();

        if (!$pipeline) {
            return response()->json([
                'data' => [
                    'status' => DealRottingService::STATUS_FRESH,
                    'days_inactive' => 0,
                    'threshold_days' => null,
                    'percentage' => 0,
                    'color' => 'green',
                    'message' => 'No pipeline configured for this module',
                ],
            ]);
        }

        $stageFieldName = $pipeline->stage_field_api_name;
        $stageId = $record->data[$stageFieldName] ?? null;

        if (!$stageId) {
            return response()->json([
                'data' => [
                    'status' => DealRottingService::STATUS_FRESH,
                    'days_inactive' => 0,
                    'threshold_days' => null,
                    'percentage' => 0,
                    'color' => 'green',
                    'message' => 'Record not in a pipeline stage',
                ],
            ]);
        }

        $stage = Stage::find($stageId);

        if (!$stage || !$stage->rotting_days) {
            return response()->json([
                'data' => [
                    'status' => DealRottingService::STATUS_FRESH,
                    'days_inactive' => 0,
                    'threshold_days' => null,
                    'percentage' => 0,
                    'color' => 'green',
                    'message' => 'No rotting threshold configured for this stage',
                ],
            ]);
        }

        // Get user's settings for this pipeline
        $settings = RottingAlertSetting::getEffectiveForUser(Auth::id(), $pipeline->id);
        $rotStatus = $this->rottingService->getRecordRotStatus($record, $stage, $settings->exclude_weekends);

        return response()->json([
            'data' => $rotStatus,
        ]);
    }

    /**
     * Get summary stats for a pipeline.
     */
    public function summary(int $pipelineId): JsonResponse
    {
        $pipeline = Pipeline::findOrFail($pipelineId);

        // Get user's settings
        $settings = RottingAlertSetting::getEffectiveForUser(Auth::id(), $pipelineId);
        $stats = $this->rottingService->getSummaryStats($pipeline, $settings->exclude_weekends);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get alerts for current user.
     */
    public function alerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'acknowledged' => 'nullable|boolean',
            'type' => 'nullable|string|in:warning,stale,rotting',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = RottingAlert::forUser(Auth::id())
            ->with(['moduleRecord', 'stage'])
            ->orderBy('sent_at', 'desc');

        if (isset($validated['acknowledged'])) {
            if ($validated['acknowledged']) {
                $query->where('acknowledged', true);
            } else {
                $query->unacknowledged();
            }
        }

        if (isset($validated['type'])) {
            $query->ofType($validated['type']);
        }

        $perPage = $validated['per_page'] ?? 25;
        $alerts = $query->paginate($perPage);

        return response()->json([
            'data' => $alerts->items(),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
            ],
        ]);
    }

    /**
     * Acknowledge an alert.
     */
    public function acknowledge(int $alertId): JsonResponse
    {
        $alert = RottingAlert::forUser(Auth::id())->findOrFail($alertId);
        $alert->acknowledge();

        return response()->json([
            'data' => $alert->fresh(),
            'message' => 'Alert acknowledged',
        ]);
    }

    /**
     * Acknowledge all alerts for current user.
     */
    public function acknowledgeAll(): JsonResponse
    {
        $count = RottingAlert::forUser(Auth::id())
            ->unacknowledged()
            ->update([
                'acknowledged' => true,
                'acknowledged_at' => now(),
            ]);

        return response()->json([
            'message' => "{$count} alerts acknowledged",
            'count' => $count,
        ]);
    }

    /**
     * Get unacknowledged alert count for current user.
     */
    public function count(): JsonResponse
    {
        $count = RottingAlert::forUser(Auth::id())
            ->unacknowledged()
            ->count();

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * Get user's rotting alert settings.
     */
    public function settings(Request $request): JsonResponse
    {
        $pipelineId = $request->input('pipeline_id');
        $settings = RottingAlertSetting::getEffectiveForUser(Auth::id(), $pipelineId);

        return response()->json([
            'data' => [
                'id' => $settings->id,
                'user_id' => $settings->user_id,
                'pipeline_id' => $settings->pipeline_id,
                'email_digest_enabled' => $settings->email_digest_enabled,
                'digest_frequency' => $settings->digest_frequency,
                'in_app_notifications' => $settings->in_app_notifications,
                'exclude_weekends' => $settings->exclude_weekends,
            ],
        ]);
    }

    /**
     * Update user's rotting alert settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pipeline_id' => 'nullable|integer|exists:pipelines,id',
            'email_digest_enabled' => 'nullable|boolean',
            'digest_frequency' => 'nullable|string|in:daily,weekly,none',
            'in_app_notifications' => 'nullable|boolean',
            'exclude_weekends' => 'nullable|boolean',
        ]);

        $userId = Auth::id();
        $pipelineId = $validated['pipeline_id'] ?? null;

        $settings = RottingAlertSetting::updateOrCreate(
            [
                'user_id' => $userId,
                'pipeline_id' => $pipelineId,
            ],
            array_filter([
                'email_digest_enabled' => $validated['email_digest_enabled'] ?? null,
                'digest_frequency' => $validated['digest_frequency'] ?? null,
                'in_app_notifications' => $validated['in_app_notifications'] ?? null,
                'exclude_weekends' => $validated['exclude_weekends'] ?? null,
            ], fn ($v) => $v !== null)
        );

        return response()->json([
            'data' => $settings,
            'message' => 'Settings updated successfully',
        ]);
    }

    /**
     * Configure rotting threshold for a stage.
     */
    public function configureStage(Request $request, int $pipelineId, int $stageId): JsonResponse
    {
        $validated = $request->validate([
            'rotting_days' => 'required|integer|min:1|max:365',
        ]);

        $stage = Stage::where('pipeline_id', $pipelineId)
            ->where('id', $stageId)
            ->firstOrFail();

        $stage->update([
            'rotting_days' => $validated['rotting_days'],
        ]);

        return response()->json([
            'data' => $stage->fresh(),
            'message' => 'Stage rotting threshold configured',
        ]);
    }

    /**
     * Remove rotting threshold for a stage.
     */
    public function removeStageConfig(int $pipelineId, int $stageId): JsonResponse
    {
        $stage = Stage::where('pipeline_id', $pipelineId)
            ->where('id', $stageId)
            ->firstOrFail();

        $stage->update([
            'rotting_days' => null,
        ]);

        return response()->json([
            'data' => $stage->fresh(),
            'message' => 'Stage rotting threshold removed',
        ]);
    }

    /**
     * Record activity for a deal (resets rotting timer).
     */
    public function recordActivity(int $recordId): JsonResponse
    {
        $record = ModuleRecord::findOrFail($recordId);
        $this->rottingService->recordActivity($record);

        return response()->json([
            'data' => [
                'last_activity_at' => $record->fresh()->last_activity_at->toIso8601String(),
            ],
            'message' => 'Activity recorded',
        ]);
    }
}
