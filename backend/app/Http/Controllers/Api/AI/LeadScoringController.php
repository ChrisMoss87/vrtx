<?php

namespace App\Http\Controllers\Api\AI;

use App\Application\Services\AI\AIApplicationService;
use App\Domain\LeadScoring\Repositories\ScoringModelRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\AI\LeadScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeadScoringController extends Controller
{
    // Validation constants
    private const VALID_GRADES = ['A', 'B', 'C', 'D', 'F'];
    private const VALID_FACTOR_TYPES = ['field_value', 'field_filled', 'activity_count', 'recency', 'custom'];
    private const MIN_POINTS = -100;
    private const MAX_POINTS = 100;
    private const MIN_WEIGHT = 0;
    private const MAX_WEIGHT = 10;

    public function __construct(
        protected AIApplicationService $aiApplicationService,
        protected LeadScoringService $scoringService,
        protected ScoringModelRepositoryInterface $scoringModelRepository
    ) {}

    /**
     * Get scoring models
     */
    public function models(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->module) {
            $filters['target_module'] = $request->module;
        }

        $filters['sort_by'] = 'name';
        $filters['sort_dir'] = 'asc';

        $result = $this->scoringModelRepository->listScoringModels($filters, 1000, 1);

        return response()->json([
            'models' => $result->items,
        ]);
    }

    /**
     * Get a specific scoring model
     */
    public function getModel(int $id): JsonResponse
    {
        $model = $this->scoringModelRepository->getScoringModelWithFactors($id);

        if (!$model) {
            abort(404, 'Scoring model not found');
        }

        return response()->json([
            'model' => $model,
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
            'target_module' => 'required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'model_type' => 'sometimes|string',
            'factors' => 'sometimes|array',
            'factors.*.id' => 'sometimes|integer',
            'factors.*.name' => 'required|string|max:255',
            'factors.*.factor_type' => 'required|in:' . implode(',', self::VALID_FACTOR_TYPES),
            'factors.*.category' => 'sometimes|nullable|string',
            'factors.*.config' => 'sometimes|nullable|array',
            'factors.*.weight' => 'sometimes|numeric|min:' . self::MIN_WEIGHT . '|max:' . self::MAX_WEIGHT,
            'factors.*.max_points' => 'sometimes|integer|min:' . self::MIN_POINTS . '|max:' . self::MAX_POINTS,
            'factors.*.is_active' => 'sometimes|boolean',
            'factors.*.display_order' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'target_module' => $request->target_module,
            'model_type' => $request->model_type ?? 'rule_based',
            'features' => $request->features ?? [],
            'weights' => $request->weights ?? [],
            'factors' => $request->factors ?? [],
        ];

        if ($request->id) {
            $model = $this->scoringModelRepository->updateScoringModel($request->id, $data);
            $message = 'Scoring model updated successfully';
        } else {
            $model = $this->scoringModelRepository->createScoringModel($data);
            $message = 'Scoring model created successfully';
        }

        return response()->json([
            'message' => $message,
            'model' => $model,
        ]);
    }

    /**
     * Delete scoring model
     */
    public function deleteModel(int $id): JsonResponse
    {
        $deleted = $this->scoringModelRepository->deleteScoringModel($id);

        if (!$deleted) {
            abort(404, 'Scoring model not found');
        }

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
            'model_id' => 'sometimes|integer|exists:scoring_models,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $score = $this->scoringModelRepository->calculateScore(
                $request->module,
                $request->record_id,
                $request->model_id
            );

            return response()->json([
                'score' => $score,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
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
            'record_ids' => 'required|array',
            'record_ids.*' => 'integer',
            'model_id' => 'sometimes|integer|exists:scoring_models,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $results = $this->scoringModelRepository->bulkCalculateScores(
                $request->module,
                $request->record_ids,
                $request->model_id
            );

            return response()->json([
                'message' => "Successfully scored {$results['success']} records",
                'success_count' => count($results['success']),
                'failed_count' => count($results['failed']),
                'results' => $results,
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
    public function getRecordScore(string $module, int $recordId, Request $request): JsonResponse
    {
        $modelId = $request->input('model_id');
        $score = $this->scoringModelRepository->getScoreForRecord($module, $recordId, $modelId);

        if (!$score) {
            return response()->json([
                'score' => null,
                'message' => 'Record has not been scored yet',
            ]);
        }

        return response()->json([
            'score' => $score,
        ]);
    }

    /**
     * Get score history for a record
     */
    public function getScoreHistory(string $module, int $recordId, Request $request): JsonResponse
    {
        $modelId = $request->input('model_id');

        // First get the score for the record
        $score = $this->scoringModelRepository->getScoreForRecord($module, $recordId, $modelId);

        if (!$score) {
            return response()->json([
                'history' => [],
                'message' => 'No score found for this record',
            ]);
        }

        // Then get the history for that score
        $history = $this->scoringModelRepository->getScoreHistory($score['id']);

        return response()->json([
            'history' => $history,
        ]);
    }

    /**
     * Get scoring statistics
     */
    public function statistics(string $module, Request $request): JsonResponse
    {
        $modelId = $request->input('model_id');

        $distribution = $this->scoringModelRepository->getScoreDistribution($module, $modelId);

        return response()->json([
            'distribution' => $distribution['distribution'],
            'total' => $distribution['total'],
        ]);
    }

    /**
     * Get top scored records
     */
    public function topScored(string $module, Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 10);
        $modelId = $request->input('model_id');

        $scores = $this->scoringModelRepository->getTopScoredRecords($module, $limit, $modelId);

        return response()->json([
            'records' => $scores,
        ]);
    }

    /**
     * Get records by grade
     */
    public function byGrade(string $module, string $grade, Request $request): JsonResponse
    {
        if (!in_array($grade, self::VALID_GRADES)) {
            return response()->json(['error' => 'Invalid grade'], 400);
        }

        $modelId = $request->input('model_id');
        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);

        $filters = [
            'record_module' => $module,
            'grade' => $grade,
        ];

        if ($modelId) {
            $filters['model_id'] = $modelId;
        }

        $result = $this->scoringModelRepository->listLeadScores($filters, $perPage, $page);

        return response()->json([
            'records' => $result->items,
            'total' => $result->total,
            'per_page' => $result->perPage,
            'current_page' => $result->currentPage,
            'last_page' => $result->lastPage,
        ]);
    }

}
