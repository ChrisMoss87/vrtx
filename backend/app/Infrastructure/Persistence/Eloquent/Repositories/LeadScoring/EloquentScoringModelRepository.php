<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\LeadScoring;

use App\Domain\LeadScoring\Entities\ScoringModel;
use App\Domain\LeadScoring\Repositories\ScoringModelRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Models\LeadScore as EloquentLeadScore;
use App\Models\LeadScoreHistory as EloquentLeadScoreHistory;
use App\Models\ModuleRecord as EloquentModuleRecord;
use App\Models\ScoringFactor as EloquentScoringFactor;
use App\Models\ScoringModel as EloquentScoringModel;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class EloquentScoringModelRepository implements ScoringModelRepositoryInterface
{
    public function findById(int $id): ?ScoringModel
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(ScoringModel $entity): ScoringModel
    {
        // TODO: Implement with Eloquent model
        return $entity;
    }

    public function delete(int $id): bool
    {
        // TODO: Implement with Eloquent model
        return false;
    }

    // =========================================================================
    // SCORING MODEL QUERY METHODS
    // =========================================================================

    public function listScoringModels(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = EloquentScoringModel::query()->withCount(['factors', 'scores']);

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

        $total = $query->count();
        $items = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn($model) => $this->modelToArray($model))
            ->toArray();

        return PaginatedResult::create($items, $total, $perPage, $page);
    }

    public function getScoringModelWithFactors(int $modelId): ?array
    {
        $model = EloquentScoringModel::with([
            'factors' => fn($q) => $q->orderBy('display_order'),
        ])->withCount('scores')->find($modelId);

        if (!$model) {
            return null;
        }

        return $this->modelToArray($model);
    }

    public function getDefaultModelForModule(string $module): ?array
    {
        $model = EloquentScoringModel::getDefaultForModule($module);

        if (!$model) {
            return null;
        }

        return $this->modelToArray($model);
    }

    public function createScoringModel(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $model = EloquentScoringModel::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'target_module' => $data['target_module'],
                'status' => EloquentScoringModel::STATUS_DRAFT,
                'model_type' => $data['model_type'] ?? EloquentScoringModel::TYPE_RULE_BASED,
                'features' => $data['features'] ?? [],
                'weights' => $data['weights'] ?? [],
                'is_default' => false,
            ]);

            // Create factors if provided
            if (!empty($data['factors'])) {
                foreach ($data['factors'] as $index => $factorData) {
                    $model->factors()->create([
                        'name' => $factorData['name'],
                        'category' => $factorData['category'] ?? EloquentScoringFactor::CATEGORY_DEMOGRAPHIC,
                        'factor_type' => $factorData['factor_type'] ?? EloquentScoringFactor::TYPE_FIELD_VALUE,
                        'config' => $factorData['config'] ?? [],
                        'weight' => $factorData['weight'] ?? 1,
                        'max_points' => $factorData['max_points'] ?? 10,
                        'is_active' => $factorData['is_active'] ?? true,
                        'display_order' => $factorData['display_order'] ?? $index,
                    ]);
                }
            }

            return $this->modelToArray($model->load(['factors']));
        });
    }

    public function updateScoringModel(int $modelId, array $data): array
    {
        $model = EloquentScoringModel::findOrFail($modelId);

        $model->update([
            'name' => $data['name'] ?? $model->name,
            'description' => $data['description'] ?? $model->description,
            'features' => $data['features'] ?? $model->features,
            'weights' => $data['weights'] ?? $model->weights,
        ]);

        return $this->modelToArray($model->fresh(['factors']));
    }

    public function deleteScoringModel(int $modelId): bool
    {
        $model = EloquentScoringModel::findOrFail($modelId);

        return DB::transaction(function () use ($model) {
            // Delete associated scores and history
            $scoreIds = $model->scores()->pluck('id');
            EloquentLeadScoreHistory::whereIn('lead_score_id', $scoreIds)->delete();
            $model->scores()->delete();

            // Delete factors
            $model->factors()->delete();

            return $model->delete();
        });
    }

    public function duplicateScoringModel(int $modelId): array
    {
        $source = EloquentScoringModel::with(['factors'])->findOrFail($modelId);

        return DB::transaction(function () use ($source) {
            $newModel = EloquentScoringModel::create([
                'name' => "{$source->name} (Copy)",
                'description' => $source->description,
                'target_module' => $source->target_module,
                'status' => EloquentScoringModel::STATUS_DRAFT,
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

            return $this->modelToArray($newModel->load(['factors']));
        });
    }

    public function activateScoringModel(int $modelId): array
    {
        $model = EloquentScoringModel::findOrFail($modelId);

        if ($model->factors()->active()->count() === 0) {
            throw new \RuntimeException('Cannot activate model without active factors');
        }

        $model->update(['status' => EloquentScoringModel::STATUS_ACTIVE]);

        return $this->modelToArray($model);
    }

    public function archiveScoringModel(int $modelId): array
    {
        $model = EloquentScoringModel::findOrFail($modelId);

        if ($model->is_default) {
            throw new \RuntimeException('Cannot archive the default model');
        }

        $model->update(['status' => EloquentScoringModel::STATUS_ARCHIVED]);

        return $this->modelToArray($model);
    }

    public function setModelAsDefault(int $modelId): array
    {
        $model = EloquentScoringModel::findOrFail($modelId);

        if ($model->status !== EloquentScoringModel::STATUS_ACTIVE) {
            throw new \RuntimeException('Only active models can be set as default');
        }

        $model->setAsDefault();

        return $this->modelToArray($model->fresh());
    }

    // =========================================================================
    // SCORING FACTOR METHODS
    // =========================================================================

    public function addFactor(int $modelId, array $data): array
    {
        $model = EloquentScoringModel::findOrFail($modelId);
        $maxOrder = $model->factors()->max('display_order') ?? 0;

        $factor = $model->factors()->create([
            'name' => $data['name'],
            'category' => $data['category'] ?? EloquentScoringFactor::CATEGORY_DEMOGRAPHIC,
            'factor_type' => $data['factor_type'] ?? EloquentScoringFactor::TYPE_FIELD_VALUE,
            'config' => $data['config'] ?? [],
            'weight' => $data['weight'] ?? 1,
            'max_points' => $data['max_points'] ?? 10,
            'is_active' => $data['is_active'] ?? true,
            'display_order' => $data['display_order'] ?? $maxOrder + 1,
        ]);

        return $this->factorToArray($factor);
    }

    public function updateFactor(int $factorId, array $data): array
    {
        $factor = EloquentScoringFactor::findOrFail($factorId);

        $factor->update([
            'name' => $data['name'] ?? $factor->name,
            'category' => $data['category'] ?? $factor->category,
            'factor_type' => $data['factor_type'] ?? $factor->factor_type,
            'config' => $data['config'] ?? $factor->config,
            'weight' => $data['weight'] ?? $factor->weight,
            'max_points' => $data['max_points'] ?? $factor->max_points,
            'is_active' => $data['is_active'] ?? $factor->is_active,
        ]);

        return $this->factorToArray($factor->fresh());
    }

    public function deleteFactor(int $factorId): bool
    {
        return EloquentScoringFactor::findOrFail($factorId)->delete();
    }

    public function reorderFactors(int $modelId, array $factorOrder): array
    {
        return DB::transaction(function () use ($modelId, $factorOrder) {
            foreach ($factorOrder as $order => $factorId) {
                EloquentScoringFactor::where('id', $factorId)
                    ->where('model_id', $modelId)
                    ->update(['display_order' => $order]);
            }

            return EloquentScoringFactor::where('model_id', $modelId)
                ->orderBy('display_order')
                ->get()
                ->map(fn($factor) => $this->factorToArray($factor))
                ->toArray();
        });
    }

    public function toggleFactorActive(int $factorId): array
    {
        $factor = EloquentScoringFactor::findOrFail($factorId);
        $factor->update(['is_active' => !$factor->is_active]);
        return $this->factorToArray($factor->fresh());
    }

    // =========================================================================
    // LEAD SCORE METHODS
    // =========================================================================

    public function listLeadScores(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = EloquentLeadScore::query()->with(['scoringModel']);

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

        $total = $query->count();
        $items = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn($score) => $this->leadScoreToArray($score))
            ->toArray();

        return PaginatedResult::create($items, $total, $perPage, $page);
    }

    public function getLeadScore(int $scoreId): ?array
    {
        $score = EloquentLeadScore::with([
            'scoringModel',
            'history' => fn($q) => $q->limit(30),
        ])->find($scoreId);

        if (!$score) {
            return null;
        }

        return $this->leadScoreToArray($score);
    }

    public function getScoreForRecord(string $module, int $recordId, ?int $modelId = null): ?array
    {
        $query = EloquentLeadScore::where('record_module', $module)
            ->where('record_id', $recordId);

        if ($modelId) {
            $query->where('model_id', $modelId);
        } else {
            // Get score from default model
            $defaultModel = EloquentScoringModel::getDefaultForModule($module);
            if ($defaultModel) {
                $query->where('model_id', $defaultModel->id);
            }
        }

        $score = $query->with(['scoringModel', 'history'])->first();

        if (!$score) {
            return null;
        }

        return $this->leadScoreToArray($score);
    }

    public function calculateScore(string $module, int $recordId, ?int $modelId = null): array
    {
        // Get the model
        $model = $modelId
            ? EloquentScoringModel::findOrFail($modelId)
            : EloquentScoringModel::getDefaultForModule($module);

        if (!$model) {
            throw new \RuntimeException("No scoring model found for module: {$module}");
        }

        if ($model->status !== EloquentScoringModel::STATUS_ACTIVE) {
            throw new \RuntimeException('Scoring model is not active');
        }

        // Get the record
        $record = EloquentModuleRecord::where('module_id', function ($q) use ($module) {
            $q->select('id')->from('modules')->where('api_name', $module)->limit(1);
        })->findOrFail($recordId);

        // Calculate score
        $result = $model->calculateScore($record->data ?? []);

        // Create or update lead score
        $leadScore = EloquentLeadScore::updateOrCreate(
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
            EloquentLeadScoreHistory::create([
                'lead_score_id' => $leadScore->id,
                'score' => $result['score'],
                'grade' => $result['grade'],
                'change_reason' => 'Score recalculated',
            ]);
        }

        return $this->leadScoreToArray($leadScore->fresh(['scoringModel']));
    }

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
                    'score_id' => $score['id'],
                    'score' => $score['score'],
                    'grade' => $score['grade'],
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

    public function recalculateAllScores(int $modelId): int
    {
        $model = EloquentScoringModel::findOrFail($modelId);
        $count = 0;

        $model->scores()->each(function (EloquentLeadScore $score) use (&$count) {
            try {
                $this->calculateScore($score->record_module, $score->record_id, $score->model_id);
                $count++;
            } catch (\Exception $e) {
                // Log error but continue
            }
        });

        return $count;
    }

    public function getScoreHistory(int $scoreId, int $limit = 30): array
    {
        return EloquentLeadScoreHistory::where('lead_score_id', $scoreId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($history) => [
                'id' => $history->id,
                'lead_score_id' => $history->lead_score_id,
                'score' => $history->score,
                'grade' => $history->grade,
                'change_reason' => $history->change_reason,
                'created_at' => $history->created_at?->toISOString(),
            ])
            ->toArray();
    }

    public function getScoreTrend(int $scoreId, int $days = 30): array
    {
        $score = EloquentLeadScore::findOrFail($scoreId);
        return $score->getTrend($days);
    }

    // =========================================================================
    // ANALYTICS METHODS
    // =========================================================================

    public function getModelStats(int $modelId): array
    {
        $model = EloquentScoringModel::findOrFail($modelId);
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

    public function getTopScoredRecords(string $module, int $limit = 10, ?int $modelId = null): array
    {
        $query = EloquentLeadScore::where('record_module', $module);

        if ($modelId) {
            $query->where('model_id', $modelId);
        }

        return $query->orderByDesc('score')
            ->limit($limit)
            ->get()
            ->map(fn($score) => $this->leadScoreToArray($score))
            ->toArray();
    }

    public function getScoreDistribution(string $module, ?int $modelId = null): array
    {
        $query = EloquentLeadScore::where('record_module', $module);

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
            if ($s <= 20) {
                $buckets['0-20']++;
            } elseif ($s <= 40) {
                $buckets['21-40']++;
            } elseif ($s <= 60) {
                $buckets['41-60']++;
            } elseif ($s <= 80) {
                $buckets['61-80']++;
            } else {
                $buckets['81-100']++;
            }
        }

        return [
            'distribution' => $buckets,
            'total' => $scores->count(),
        ];
    }

    public function getConversionAnalysis(string $module, string $conversionField, ?int $modelId = null): array
    {
        $query = EloquentLeadScore::where('record_module', $module);

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

    public function getScoreChangesOverTime(int $modelId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $changes = EloquentLeadScoreHistory::whereHas('leadScore', fn($q) => $q->where('model_id', $modelId))
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

    public function getScoreImprovements(string $module, int $days = 7, ?int $modelId = null): array
    {
        $startDate = Carbon::now()->subDays($days);

        return EloquentLeadScore::where('record_module', $module)
            ->when($modelId, fn($q) => $q->where('model_id', $modelId))
            ->whereHas('history', function ($q) use ($startDate) {
                $q->where('created_at', '>=', $startDate);
            })
            ->get()
            ->map(function (EloquentLeadScore $score) use ($startDate) {
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
            ->values()
            ->toArray();
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function modelToArray(EloquentScoringModel $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description,
            'target_module' => $model->target_module,
            'status' => $model->status,
            'model_type' => $model->model_type,
            'features' => $model->features,
            'weights' => $model->weights,
            'accuracy' => $model->accuracy,
            'training_records' => $model->training_records,
            'trained_at' => $model->trained_at?->toISOString(),
            'is_default' => $model->is_default,
            'factors_count' => $model->factors_count ?? null,
            'scores_count' => $model->scores_count ?? null,
            'factors' => $model->relationLoaded('factors')
                ? $model->factors->map(fn($factor) => $this->factorToArray($factor))->toArray()
                : null,
            'created_at' => $model->created_at?->toISOString(),
            'updated_at' => $model->updated_at?->toISOString(),
        ];
    }

    private function factorToArray(EloquentScoringFactor $factor): array
    {
        return [
            'id' => $factor->id,
            'model_id' => $factor->model_id,
            'name' => $factor->name,
            'category' => $factor->category,
            'factor_type' => $factor->factor_type,
            'config' => $factor->config,
            'weight' => $factor->weight,
            'max_points' => $factor->max_points,
            'is_active' => $factor->is_active,
            'display_order' => $factor->display_order,
            'created_at' => $factor->created_at?->toISOString(),
            'updated_at' => $factor->updated_at?->toISOString(),
        ];
    }

    private function leadScoreToArray(EloquentLeadScore $score): array
    {
        return [
            'id' => $score->id,
            'model_id' => $score->model_id,
            'record_module' => $score->record_module,
            'record_id' => $score->record_id,
            'score' => $score->score,
            'grade' => $score->grade,
            'factor_breakdown' => $score->factor_breakdown,
            'explanation' => $score->explanation,
            'conversion_probability' => $score->conversion_probability,
            'calculated_at' => $score->calculated_at?->toISOString(),
            'scoring_model' => $score->relationLoaded('scoringModel') && $score->scoringModel
                ? $this->modelToArray($score->scoringModel)
                : null,
            'history' => $score->relationLoaded('history')
                ? $score->history->map(fn($h) => [
                    'id' => $h->id,
                    'lead_score_id' => $h->lead_score_id,
                    'score' => $h->score,
                    'grade' => $h->grade,
                    'change_reason' => $h->change_reason,
                    'created_at' => $h->created_at?->toISOString(),
                ])->toArray()
                : null,
            'created_at' => $score->created_at?->toISOString(),
            'updated_at' => $score->updated_at?->toISOString(),
        ];
    }

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
