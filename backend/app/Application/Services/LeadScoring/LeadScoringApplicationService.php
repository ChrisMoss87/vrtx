<?php

declare(strict_types=1);

namespace App\Application\Services\LeadScoring;

use App\Domain\LeadScoring\Repositories\ScoringModelRepositoryInterface;
use App\Models\LeadScore;
use App\Models\LeadScoreHistory;
use App\Models\ModuleRecord;
use App\Models\ScoringFactor;
use App\Models\ScoringModel;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeadScoringApplicationService
{
    public function __construct(
        private ScoringModelRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // SCORING MODEL USE CASES
    // =========================================================================

    /**
     * List scoring models
     */
    public function listScoringModels(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ScoringModel::query()->withCount(['factors', 'scores']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter active only
        if (!empty($filters['active_only'])) {
            $query->active();
        }

        // Filter by target module
        if (!empty($filters['target_module'])) {
            $query->where('target_module', $filters['target_module']);
        }

        // Filter by model type
        if (!empty($filters['model_type'])) {
            $query->where('model_type', $filters['model_type']);
        }

        // Search
        if (!empty($filters['search'])) {
            $query->where('name', 'ilike', "%{$filters['search']}%");
        }

        // Sort
        $sortField = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortField, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a scoring model with factors
     */
    public function getScoringModel(int $modelId): ?ScoringModel
    {
        return ScoringModel::with([
            'factors' => fn($q) => $q->orderBy('display_order'),
        ])->withCount('scores')->find($modelId);
    }

    /**
     * Get default model for a module
     */
    public function getDefaultModel(string $module): ?ScoringModel
    {
        return ScoringModel::getDefaultForModule($module);
    }

    /**
     * Create a scoring model
     */
    public function createScoringModel(array $data): ScoringModel
    {
        return DB::transaction(function () use ($data) {
            $model = ScoringModel::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'target_module' => $data['target_module'],
                'status' => ScoringModel::STATUS_DRAFT,
                'model_type' => $data['model_type'] ?? ScoringModel::TYPE_RULE_BASED,
                'features' => $data['features'] ?? [],
                'weights' => $data['weights'] ?? [],
                'is_default' => false,
            ]);

            // Create factors if provided
            if (!empty($data['factors'])) {
                foreach ($data['factors'] as $index => $factorData) {
                    $model->factors()->create([
                        'name' => $factorData['name'],
                        'category' => $factorData['category'] ?? ScoringFactor::CATEGORY_DEMOGRAPHIC,
                        'factor_type' => $factorData['factor_type'] ?? ScoringFactor::TYPE_FIELD_VALUE,
                        'config' => $factorData['config'] ?? [],
                        'weight' => $factorData['weight'] ?? 1,
                        'max_points' => $factorData['max_points'] ?? 10,
                        'is_active' => $factorData['is_active'] ?? true,
                        'display_order' => $factorData['display_order'] ?? $index,
                    ]);
                }
            }

            return $model->load(['factors']);
        });
    }

    /**
     * Update a scoring model
     */
    public function updateScoringModel(int $modelId, array $data): ScoringModel
    {
        $model = ScoringModel::findOrFail($modelId);

        $model->update([
            'name' => $data['name'] ?? $model->name,
            'description' => $data['description'] ?? $model->description,
            'features' => $data['features'] ?? $model->features,
            'weights' => $data['weights'] ?? $model->weights,
        ]);

        return $model->fresh(['factors']);
    }

    /**
     * Delete a scoring model
     */
    public function deleteScoringModel(int $modelId): bool
    {
        $model = ScoringModel::findOrFail($modelId);

        return DB::transaction(function () use ($model) {
            // Delete associated scores and history
            $scoreIds = $model->scores()->pluck('id');
            LeadScoreHistory::whereIn('lead_score_id', $scoreIds)->delete();
            $model->scores()->delete();

            // Delete factors
            $model->factors()->delete();

            return $model->delete();
        });
    }

    /**
     * Duplicate a scoring model
     */
    public function duplicateScoringModel(int $modelId): ScoringModel
    {
        $source = ScoringModel::with(['factors'])->findOrFail($modelId);

        return DB::transaction(function () use ($source) {
            $newModel = ScoringModel::create([
                'name' => "{$source->name} (Copy)",
                'description' => $source->description,
                'target_module' => $source->target_module,
                'status' => ScoringModel::STATUS_DRAFT,
                'model_type' => $source->model_type,
                'features' => $source->features,
                'weights' => $source->weights,
                'is_default' => false,
            ]);

            foreach ($source->factors as $factor) {
                $newModel->factors()->create([
                    'name' => $factor->name,
                    'category' => $factor->category,
                    'factor_type' => $factor->factor_type,
                    'config' => $factor->config,
                    'weight' => $factor->weight,
                    'max_points' => $factor->max_points,
                    'is_active' => $factor->is_active,
                    'display_order' => $factor->display_order,
                ]);
            }

            return $newModel->load(['factors']);
        });
    }

    /**
     * Activate a scoring model
     */
    public function activateScoringModel(int $modelId): ScoringModel
    {
        $model = ScoringModel::findOrFail($modelId);

        if ($model->factors()->active()->count() === 0) {
            throw new \RuntimeException('Cannot activate model without active factors');
        }

        $model->update(['status' => ScoringModel::STATUS_ACTIVE]);

        return $model;
    }

    /**
     * Archive a scoring model
     */
    public function archiveScoringModel(int $modelId): ScoringModel
    {
        $model = ScoringModel::findOrFail($modelId);

        if ($model->is_default) {
            throw new \RuntimeException('Cannot archive the default model');
        }

        $model->update(['status' => ScoringModel::STATUS_ARCHIVED]);

        return $model;
    }

    /**
     * Set model as default for its module
     */
    public function setAsDefault(int $modelId): ScoringModel
    {
        $model = ScoringModel::findOrFail($modelId);

        if ($model->status !== ScoringModel::STATUS_ACTIVE) {
            throw new \RuntimeException('Only active models can be set as default');
        }

        $model->setAsDefault();

        return $model->fresh();
    }

    // =========================================================================
    // SCORING FACTOR USE CASES
    // =========================================================================

    /**
     * Add factor to a model
     */
    public function addFactor(int $modelId, array $data): ScoringFactor
    {
        $model = ScoringModel::findOrFail($modelId);
        $maxOrder = $model->factors()->max('display_order') ?? 0;

        return $model->factors()->create([
            'name' => $data['name'],
            'category' => $data['category'] ?? ScoringFactor::CATEGORY_DEMOGRAPHIC,
            'factor_type' => $data['factor_type'] ?? ScoringFactor::TYPE_FIELD_VALUE,
            'config' => $data['config'] ?? [],
            'weight' => $data['weight'] ?? 1,
            'max_points' => $data['max_points'] ?? 10,
            'is_active' => $data['is_active'] ?? true,
            'display_order' => $data['display_order'] ?? $maxOrder + 1,
        ]);
    }

    /**
     * Update a factor
     */
    public function updateFactor(int $factorId, array $data): ScoringFactor
    {
        $factor = ScoringFactor::findOrFail($factorId);

        $factor->update([
            'name' => $data['name'] ?? $factor->name,
            'category' => $data['category'] ?? $factor->category,
            'factor_type' => $data['factor_type'] ?? $factor->factor_type,
            'config' => $data['config'] ?? $factor->config,
            'weight' => $data['weight'] ?? $factor->weight,
            'max_points' => $data['max_points'] ?? $factor->max_points,
            'is_active' => $data['is_active'] ?? $factor->is_active,
        ]);

        return $factor->fresh();
    }

    /**
     * Delete a factor
     */
    public function deleteFactor(int $factorId): bool
    {
        return ScoringFactor::findOrFail($factorId)->delete();
    }

    /**
     * Reorder factors
     */
    public function reorderFactors(int $modelId, array $factorOrder): Collection
    {
        return DB::transaction(function () use ($modelId, $factorOrder) {
            foreach ($factorOrder as $order => $factorId) {
                ScoringFactor::where('id', $factorId)
                    ->where('model_id', $modelId)
                    ->update(['display_order' => $order]);
            }

            return ScoringFactor::where('model_id', $modelId)
                ->orderBy('display_order')
                ->get();
        });
    }

    /**
     * Toggle factor active status
     */
    public function toggleFactorActive(int $factorId): ScoringFactor
    {
        $factor = ScoringFactor::findOrFail($factorId);
        $factor->update(['is_active' => !$factor->is_active]);
        return $factor->fresh();
    }

    // =========================================================================
    // LEAD SCORE USE CASES
    // =========================================================================

    /**
     * List lead scores
     */
    public function listLeadScores(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = LeadScore::query()->with(['scoringModel']);

        // Filter by model
        if (!empty($filters['model_id'])) {
            $query->where('model_id', $filters['model_id']);
        }

        // Filter by module
        if (!empty($filters['record_module'])) {
            $query->where('record_module', $filters['record_module']);
        }

        // Filter by grade
        if (!empty($filters['grade'])) {
            $query->grade($filters['grade']);
        }

        // Filter by grades (multiple)
        if (!empty($filters['grades']) && is_array($filters['grades'])) {
            $query->whereIn('grade', $filters['grades']);
        }

        // Filter high scores
        if (!empty($filters['high_scores_only'])) {
            $query->highScores();
        }

        // Filter low scores
        if (!empty($filters['low_scores_only'])) {
            $query->lowScores();
        }

        // Filter by score range
        if (!empty($filters['min_score'])) {
            $query->where('score', '>=', $filters['min_score']);
        }
        if (!empty($filters['max_score'])) {
            $query->where('score', '<=', $filters['max_score']);
        }

        // Sort
        $sortField = $filters['sort_by'] ?? 'score';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get lead score for a record
     */
    public function getLeadScore(int $scoreId): ?LeadScore
    {
        return LeadScore::with([
            'scoringModel',
            'history' => fn($q) => $q->limit(30),
        ])->find($scoreId);
    }

    /**
     * Get score for a specific record
     */
    public function getScoreForRecord(string $module, int $recordId, ?int $modelId = null): ?LeadScore
    {
        $query = LeadScore::where('record_module', $module)
            ->where('record_id', $recordId);

        if ($modelId) {
            $query->where('model_id', $modelId);
        } else {
            // Get score from default model
            $defaultModel = ScoringModel::getDefaultForModule($module);
            if ($defaultModel) {
                $query->where('model_id', $defaultModel->id);
            }
        }

        return $query->with(['scoringModel', 'history'])->first();
    }

    /**
     * Calculate score for a record
     */
    public function calculateScore(string $module, int $recordId, ?int $modelId = null): LeadScore
    {
        // Get the model
        $model = $modelId
            ? ScoringModel::findOrFail($modelId)
            : ScoringModel::getDefaultForModule($module);

        if (!$model) {
            throw new \RuntimeException("No scoring model found for module: {$module}");
        }

        if ($model->status !== ScoringModel::STATUS_ACTIVE) {
            throw new \RuntimeException('Scoring model is not active');
        }

        // Get the record
        $record = ModuleRecord::where('module_id', function ($q) use ($module) {
            $q->select('id')->from('modules')->where('api_name', $module)->limit(1);
        })->findOrFail($recordId);

        // Calculate score
        $result = $model->calculateScore($record->data ?? []);

        // Create or update lead score
        $leadScore = LeadScore::updateOrCreate(
            [
                'model_id' => $model->id,
                'record_module' => $module,
                'record_id' => $recordId,
            ],
            [
                'score' => $result['score'],
                'grade' => $result['grade'],
                'factor_breakdown' => $result['breakdown'],
                'explanation' => $result['explanations'],
                'calculated_at' => now(),
            ]
        );

        // Record history if this is an update
        if (!$leadScore->wasRecentlyCreated) {
            LeadScoreHistory::create([
                'lead_score_id' => $leadScore->id,
                'score' => $result['score'],
                'grade' => $result['grade'],
                'change_reason' => 'Score recalculated',
            ]);
        }

        return $leadScore->fresh(['scoringModel']);
    }

    /**
     * Bulk calculate scores for multiple records
     */
    public function bulkCalculateScores(string $module, array $recordIds, ?int $modelId = null): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($recordIds as $recordId) {
            try {
                $score = $this->calculateScore($module, $recordId, $modelId);
                $results['success'][] = [
                    'record_id' => $recordId,
                    'score_id' => $score->id,
                    'score' => $score->score,
                    'grade' => $score->grade,
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'record_id' => $recordId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Recalculate all scores for a model
     */
    public function recalculateAllScores(int $modelId): int
    {
        $model = ScoringModel::findOrFail($modelId);
        $count = 0;

        $model->scores()->each(function (LeadScore $score) use (&$count) {
            try {
                $this->calculateScore($score->record_module, $score->record_id, $score->model_id);
                $count++;
            } catch (\Exception $e) {
                // Log error but continue
            }
        });

        return $count;
    }

    /**
     * Get score history for a record
     */
    public function getScoreHistory(int $scoreId, int $limit = 30): Collection
    {
        return LeadScoreHistory::where('lead_score_id', $scoreId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get score trend for a record
     */
    public function getScoreTrend(int $scoreId, int $days = 30): array
    {
        $score = LeadScore::findOrFail($scoreId);
        return $score->getTrend($days);
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get scoring model statistics
     */
    public function getModelStats(int $modelId): array
    {
        $model = ScoringModel::findOrFail($modelId);
        $scores = $model->scores;

        $gradeDistribution = $scores->groupBy('grade')->map->count();

        return [
            'model_id' => $modelId,
            'total_scored' => $scores->count(),
            'avg_score' => round($scores->avg('score') ?? 0, 1),
            'median_score' => $this->calculateMedian($scores->pluck('score')->toArray()),
            'grade_distribution' => [
                'A' => $gradeDistribution['A'] ?? 0,
                'B' => $gradeDistribution['B'] ?? 0,
                'C' => $gradeDistribution['C'] ?? 0,
                'D' => $gradeDistribution['D'] ?? 0,
                'F' => $gradeDistribution['F'] ?? 0,
            ],
            'high_score_count' => $scores->whereIn('grade', ['A', 'B'])->count(),
            'low_score_count' => $scores->whereIn('grade', ['D', 'F'])->count(),
            'factor_count' => $model->factors()->active()->count(),
        ];
    }

    /**
     * Get top scored records
     */
    public function getTopScoredRecords(string $module, int $limit = 10, ?int $modelId = null): Collection
    {
        $query = LeadScore::where('record_module', $module);

        if ($modelId) {
            $query->where('model_id', $modelId);
        }

        return $query->orderByDesc('score')
            ->limit($limit)
            ->get();
    }

    /**
     * Get score distribution for a module
     */
    public function getScoreDistribution(string $module, ?int $modelId = null): array
    {
        $query = LeadScore::where('record_module', $module);

        if ($modelId) {
            $query->where('model_id', $modelId);
        }

        $scores = $query->get();

        // Create buckets: 0-20, 21-40, 41-60, 61-80, 81-100
        $buckets = [
            '0-20' => 0,
            '21-40' => 0,
            '41-60' => 0,
            '61-80' => 0,
            '81-100' => 0,
        ];

        foreach ($scores as $score) {
            $s = $score->score;
            if ($s <= 20) $buckets['0-20']++;
            elseif ($s <= 40) $buckets['21-40']++;
            elseif ($s <= 60) $buckets['41-60']++;
            elseif ($s <= 80) $buckets['61-80']++;
            else $buckets['81-100']++;
        }

        return [
            'distribution' => $buckets,
            'total' => $scores->count(),
        ];
    }

    /**
     * Get conversion analysis by score range
     */
    public function getConversionAnalysis(string $module, string $conversionField, ?int $modelId = null): array
    {
        $query = LeadScore::where('record_module', $module);

        if ($modelId) {
            $query->where('model_id', $modelId);
        }

        $scores = $query->get();

        $analysis = [];
        $grades = ['A', 'B', 'C', 'D', 'F'];

        foreach ($grades as $grade) {
            $gradeScores = $scores->where('grade', $grade);
            $total = $gradeScores->count();
            $converted = 0;

            foreach ($gradeScores as $score) {
                $record = $score->getRecord();
                if ($record && !empty($record->data[$conversionField])) {
                    $converted++;
                }
            }

            $analysis[$grade] = [
                'total' => $total,
                'converted' => $converted,
                'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
            ];
        }

        return $analysis;
    }

    /**
     * Get score changes over time
     */
    public function getScoreChangesOverTime(int $modelId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $changes = LeadScoreHistory::whereHas('leadScore', fn($q) => $q->where('model_id', $modelId))
            ->where('created_at', '>=', $startDate)
            ->selectRaw("DATE(created_at) as date, AVG(score) as avg_score, COUNT(*) as count")
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return $changes->map(fn($row) => [
            'date' => $row->date,
            'avg_score' => round($row->avg_score, 1),
            'changes' => $row->count,
        ])->toArray();
    }

    /**
     * Identify score improvements
     */
    public function getScoreImprovements(string $module, int $days = 7, ?int $modelId = null): Collection
    {
        $startDate = Carbon::now()->subDays($days);

        return LeadScore::where('record_module', $module)
            ->when($modelId, fn($q) => $q->where('model_id', $modelId))
            ->whereHas('history', function ($q) use ($startDate) {
                $q->where('created_at', '>=', $startDate);
            })
            ->get()
            ->map(function (LeadScore $score) use ($startDate) {
                $oldestHistory = $score->history()
                    ->where('created_at', '>=', $startDate)
                    ->orderBy('created_at')
                    ->first();

                if (!$oldestHistory) {
                    return null;
                }

                $improvement = $score->score - $oldestHistory->score;

                return [
                    'record_id' => $score->record_id,
                    'record_module' => $score->record_module,
                    'previous_score' => $oldestHistory->score,
                    'current_score' => $score->score,
                    'improvement' => $improvement,
                    'previous_grade' => $oldestHistory->grade,
                    'current_grade' => $score->grade,
                ];
            })
            ->filter()
            ->sortByDesc('improvement')
            ->values();
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Calculate median value
     */
    private function calculateMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }
}
