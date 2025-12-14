<?php

namespace App\Http\Controllers\Api\AI;

use App\Application\Services\AI\AIApplicationService;
use App\Http\Controllers\Controller;
use App\Models\LeadScore;
use App\Models\ModuleRecord;
use App\Models\ScoringFactor;
use App\Models\ScoringModel;
use App\Services\AI\LeadScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeadScoringController extends Controller
{
    public function __construct(
        protected AIApplicationService $aiApplicationService,
        protected LeadScoringService $scoringService
    ) {}

    /**
     * Get scoring models
     */
    public function models(Request $request): JsonResponse
    {
        $models = ScoringModel::when(
            $request->module,
            fn ($q) => $q->where('module_api_name', $request->module)
        )
            ->with('factors')
            ->orderBy('name')
            ->get();

        return response()->json([
            'models' => $models->map(fn ($model) => $this->formatModel($model)),
        ]);
    }

    /**
     * Get a specific scoring model
     */
    public function getModel(int $id): JsonResponse
    {
        $model = ScoringModel::with('factors')->findOrFail($id);

        return response()->json([
            'model' => $this->formatModel($model),
        ]);
    }

    /**
     * Create/Update scoring model
     */
    public function saveModel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'sometimes|exists:scoring_models,id',
            'name' => 'required|string|max:255',
            'module_api_name' => 'required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
            'factors' => 'sometimes|array',
            'factors.*.id' => 'sometimes|exists:scoring_factors,id',
            'factors.*.name' => 'required|string|max:255',
            'factors.*.factor_type' => 'required|in:field_value,field_filled,activity_count,recency,custom',
            'factors.*.field_name' => 'sometimes|nullable|string',
            'factors.*.operator' => 'sometimes|nullable|string',
            'factors.*.value' => 'sometimes|nullable',
            'factors.*.points' => 'required|integer|min:-100|max:100',
            'factors.*.weight' => 'sometimes|numeric|min:0|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $model = DB::transaction(function () use ($request) {
            $model = $request->id
                ? ScoringModel::findOrFail($request->id)
                : new ScoringModel();

            $model->fill($request->only([
                'name',
                'module_api_name',
                'description',
                'is_active',
            ]));
            $model->save();

            // Handle factors if provided
            if ($request->has('factors')) {
                $existingFactorIds = [];

                foreach ($request->factors as $factorData) {
                    $factor = isset($factorData['id'])
                        ? ScoringFactor::find($factorData['id'])
                        : new ScoringFactor();

                    $factor->fill([
                        'scoring_model_id' => $model->id,
                        'name' => $factorData['name'],
                        'factor_type' => $factorData['factor_type'],
                        'field_name' => $factorData['field_name'] ?? null,
                        'operator' => $factorData['operator'] ?? null,
                        'value' => $factorData['value'] ?? null,
                        'points' => $factorData['points'],
                        'weight' => $factorData['weight'] ?? 1.0,
                    ]);
                    $factor->save();

                    $existingFactorIds[] = $factor->id;
                }

                // Remove factors not in the request
                ScoringFactor::where('scoring_model_id', $model->id)
                    ->whereNotIn('id', $existingFactorIds)
                    ->delete();
            }

            return $model->fresh(['factors']);
        });

        return response()->json([
            'message' => 'Scoring model saved successfully',
            'model' => $this->formatModel($model),
        ]);
    }

    /**
     * Delete scoring model
     */
    public function deleteModel(int $id): JsonResponse
    {
        $model = ScoringModel::findOrFail($id);

        DB::transaction(function () use ($model) {
            $model->factors()->delete();
            $model->delete();
        });

        return response()->json([
            'message' => 'Scoring model deleted successfully',
        ]);
    }

    /**
     * Score a single record
     */
    public function scoreRecord(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'module' => 'required|string',
            'record_id' => 'required|integer',
            'use_ai' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ModuleRecord::where('module_api_name', $request->module)
            ->findOrFail($request->record_id);

        try {
            $score = $request->use_ai
                ? $this->scoringService->scoreWithAi($record)
                : $this->scoringService->scoreRecord($record);

            if (!$score) {
                return response()->json([
                    'error' => 'No scoring model available for this module',
                ], 400);
            }

            return response()->json([
                'score' => $this->formatScore($score),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to score record',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch score records
     */
    public function batchScore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'module' => 'required|string',
            'record_ids' => 'sometimes|array',
            'record_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $scored = $this->scoringService->batchScore(
                $request->module,
                $request->record_ids
            );

            return response()->json([
                'message' => "Successfully scored {$scored} records",
                'scored_count' => $scored,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Batch scoring failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get score for a record
     */
    public function getRecordScore(string $module, int $recordId): JsonResponse
    {
        $score = LeadScore::where('record_module', $module)
            ->where('record_id', $recordId)
            ->first();

        if (!$score) {
            return response()->json([
                'score' => null,
                'message' => 'Record has not been scored yet',
            ]);
        }

        return response()->json([
            'score' => $this->formatScore($score),
        ]);
    }

    /**
     * Get score history for a record
     */
    public function getScoreHistory(string $module, int $recordId): JsonResponse
    {
        $history = $this->scoringService->getScoreHistory($module, $recordId);

        return response()->json([
            'history' => $history->map(fn ($h) => [
                'id' => $h->id,
                'score' => $h->score,
                'grade' => $h->grade,
                'change_reason' => $h->change_reason,
                'created_at' => $h->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Get scoring statistics
     */
    public function statistics(string $module): JsonResponse
    {
        return response()->json([
            'distribution' => $this->scoringService->getDistribution($module),
            'average_score' => $this->scoringService->getAverageScore($module),
        ]);
    }

    /**
     * Get top scored records
     */
    public function topScored(string $module, Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);

        $scores = $this->scoringService->getTopScored($module, $limit);

        return response()->json([
            'records' => $scores->map(fn ($score) => [
                'score' => $this->formatScore($score),
                'record' => $score->record ? [
                    'id' => $score->record->id,
                    'data' => $score->record->data,
                ] : null,
            ]),
        ]);
    }

    /**
     * Get records by grade
     */
    public function byGrade(string $module, string $grade): JsonResponse
    {
        if (!in_array($grade, ['A', 'B', 'C', 'D', 'F'])) {
            return response()->json(['error' => 'Invalid grade'], 400);
        }

        $scores = $this->scoringService->getByGrade($module, $grade);

        return response()->json([
            'records' => $scores->map(fn ($score) => [
                'score' => $this->formatScore($score),
                'record' => $score->record ? [
                    'id' => $score->record->id,
                    'data' => $score->record->data,
                ] : null,
            ]),
        ]);
    }

    /**
     * Format scoring model for response
     */
    protected function formatModel(ScoringModel $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'module_api_name' => $model->module_api_name,
            'description' => $model->description,
            'is_active' => $model->is_active,
            'factors' => $model->factors->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'factor_type' => $f->factor_type,
                'field_name' => $f->field_name,
                'operator' => $f->operator,
                'value' => $f->value,
                'points' => $f->points,
                'weight' => (float) $f->weight,
            ]),
            'created_at' => $model->created_at->toIso8601String(),
            'updated_at' => $model->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format score for response
     */
    protected function formatScore(LeadScore $score): array
    {
        return [
            'id' => $score->id,
            'record_module' => $score->record_module,
            'record_id' => $score->record_id,
            'score' => $score->score,
            'grade' => $score->grade,
            'breakdown' => $score->breakdown,
            'explanations' => $score->explanations,
            'ai_insights' => $score->ai_insights,
            'model_used' => $score->model_used,
            'last_calculated_at' => $score->last_calculated_at?->toIso8601String(),
        ];
    }
}
