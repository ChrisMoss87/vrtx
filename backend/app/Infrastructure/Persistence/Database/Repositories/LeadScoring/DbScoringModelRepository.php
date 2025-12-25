<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\LeadScoring;

use App\Domain\LeadScoring\Entities\ScoringModel;
use App\Domain\LeadScoring\Repositories\ScoringModelRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbScoringModelRepository implements ScoringModelRepositoryInterface
{
    private const TABLE_SCORING_MODELS = 'scoring_models';
    private const TABLE_SCORING_FACTORS = 'scoring_factors';
    private const TABLE_LEAD_SCORES = 'lead_scores';
    private const TABLE_LEAD_SCORE_HISTORY = 'lead_score_history';
    private const TABLE_MODULE_RECORDS = 'module_records';
    private const TABLE_MODULES = 'modules';

    // Status constants (mirrored from EloquentScoringModel)
    private const STATUS_DRAFT = 'draft';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_ARCHIVED = 'archived';

    // Type constants
    private const TYPE_RULE_BASED = 'rule_based';

    // Category constants (mirrored from EloquentScoringFactor)
    private const CATEGORY_DEMOGRAPHIC = 'demographic';

    // Factor type constants
    private const TYPE_FIELD_VALUE = 'field_value';

    public function findById(int $id): ?ScoringModel
    {
        // TODO: Implement with Query Builder
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Database model
        return [];
    }

    public function save(ScoringModel $entity): ScoringModel
    {
        // TODO: Implement with Database model
        return $entity;
    }

    public function delete(int $id): bool
    {
        // TODO: Implement with Database model
        return false;
    }

    // =========================================================================
    // SCORING MODEL QUERY METHODS
    // =========================================================================

    public function listScoringModels(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_SCORING_MODELS);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter active only
        if (!empty($filters['active_only'])) {
            $query->where('status', self::STATUS_ACTIVE);
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
        $items = $query->forPage($page, $perPage)
            ->get()
            ->map(function($model) {
                // Load counts
                $factorsCount = DB::table(self::TABLE_SCORING_FACTORS)
                    ->where('model_id', $model->id)
                    ->count();
                $scoresCount = DB::table(self::TABLE_LEAD_SCORES)
                    ->where('model_id', $model->id)
                    ->count();

                $model->factors_count = $factorsCount;
                $model->scores_count = $scoresCount;

                return $this->modelToArray($model);
            })
            ->toArray();

        return PaginatedResult::create($items, $total, $perPage, $page);
    }

    public function getScoringModelWithFactors(int $modelId): ?array
    {
        $model = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->first();

        if (!$model) {
            return null;
        }

        // Load factors
        $factors = DB::table(self::TABLE_SCORING_FACTORS)
            ->where('model_id', $modelId)
            ->orderBy('display_order')
            ->get();

        // Load scores count
        $scoresCount = DB::table(self::TABLE_LEAD_SCORES)
            ->where('model_id', $modelId)
            ->count();

        $model->factors_count = null;
        $model->scores_count = $scoresCount;
        $model->factors = $factors;

        return $this->modelToArray($model);
    }

    public function getDefaultModelForModule(string $module): ?array
    {
        $model = DB::table(self::TABLE_SCORING_MODELS)
            ->where('target_module', $module)
            ->where('is_default', true)
            ->where('status', self::STATUS_ACTIVE)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->modelToArray($model);
    }

    public function createScoringModel(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $now = now();
            $modelId = DB::table(self::TABLE_SCORING_MODELS)->insertGetId([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'target_module' => $data['target_module'],
                'status' => self::STATUS_DRAFT,
                'model_type' => $data['model_type'] ?? self::TYPE_RULE_BASED,
                'features' => json_encode($data['features'] ?? []),
                'weights' => json_encode($data['weights'] ?? []),
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Create factors if provided
            if (!empty($data['factors'])) {
                foreach ($data['factors'] as $index => $factorData) {
                    DB::table(self::TABLE_SCORING_FACTORS)->insert([
                        'model_id' => $modelId,
                        'name' => $factorData['name'],
                        'category' => $factorData['category'] ?? self::CATEGORY_DEMOGRAPHIC,
                        'factor_type' => $factorData['factor_type'] ?? self::TYPE_FIELD_VALUE,
                        'config' => json_encode($factorData['config'] ?? []),
                        'weight' => $factorData['weight'] ?? 1,
                        'max_points' => $factorData['max_points'] ?? 10,
                        'is_active' => $factorData['is_active'] ?? true,
                        'display_order' => $factorData['display_order'] ?? $index,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // Load the model with factors
            $model = DB::table(self::TABLE_SCORING_MODELS)->where('id', $modelId)->first();
            $factors = DB::table(self::TABLE_SCORING_FACTORS)
                ->where('model_id', $modelId)
                ->orderBy('display_order')
                ->get();
            $model->factors = $factors;

            return $this->modelToArray($model);
        });
    }

    public function updateScoringModel(int $modelId, array $data): array
    {
        $model = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->first();

        if (!$model) {
            throw new \RuntimeException("Scoring model not found: {$modelId}");
        }

        $updateData = [
            'name' => $data['name'] ?? $model->name,
            'description' => $data['description'] ?? $model->description,
            'features' => isset($data['features']) ? json_encode($data['features']) : $model->features,
            'weights' => isset($data['weights']) ? json_encode($data['weights']) : $model->weights,
            'updated_at' => now(),
        ];

        DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->update($updateData);

        // Load the updated model with factors
        $updatedModel = DB::table(self::TABLE_SCORING_MODELS)->where('id', $modelId)->first();
        $factors = DB::table(self::TABLE_SCORING_FACTORS)
            ->where('model_id', $modelId)
            ->orderBy('display_order')
            ->get();
        $updatedModel->factors = $factors;

        return $this->modelToArray($updatedModel);
    }

    public function deleteScoringModel(int $modelId): bool
    {
        $model = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->first();

        if (!$model) {
            throw new \RuntimeException("Scoring model not found: {$modelId}");
        }

        return DB::transaction(function () use ($modelId) {
            // Delete associated scores and history
            $scoreIds = DB::table(self::TABLE_LEAD_SCORES)
                ->where('model_id', $modelId)
                ->pluck('id');

            if ($scoreIds->isNotEmpty()) {
                DB::table(self::TABLE_LEAD_SCORE_HISTORY)
                    ->whereIn('lead_score_id', $scoreIds->toArray())
                    ->delete();
                DB::table(self::TABLE_LEAD_SCORES)
                    ->where('model_id', $modelId)
                    ->delete();
            }

            // Delete factors
            DB::table(self::TABLE_SCORING_FACTORS)
                ->where('model_id', $modelId)
                ->delete();

            // Delete model
            return DB::table(self::TABLE_SCORING_MODELS)
                ->where('id', $modelId)
                ->delete() > 0;
        });
    }

    public function duplicateScoringModel(int $modelId): array
    {
        $source = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->first();

        if (!$source) {
            throw new \RuntimeException("Scoring model not found: {$modelId}");
        }

        $factors = DB::table(self::TABLE_SCORING_FACTORS)
            ->where('model_id', $modelId)
            ->get();

        return DB::transaction(function () use ($source, $factors) {
            $now = now();
            $newModelId = DB::table(self::TABLE_SCORING_MODELS)->insertGetId([
                'name' => "{$source->name} (Copy)",
                'description' => $source->description,
                'target_module' => $source->target_module,
                'status' => self::STATUS_DRAFT,
                'model_type' => $source->model_type,
                'features' => $source->features,
                'weights' => $source->weights,
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($factors as $factor) {
                DB::table(self::TABLE_SCORING_FACTORS)->insert([
                    'model_id' => $newModelId,
                    'name' => $factor->name,
                    'category' => $factor->category,
                    'factor_type' => $factor->factor_type,
                    'config' => $factor->config,
                    'weight' => $factor->weight,
                    'max_points' => $factor->max_points,
                    'is_active' => $factor->is_active,
                    'display_order' => $factor->display_order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $newModel = DB::table(self::TABLE_SCORING_MODELS)->where('id', $newModelId)->first();
            $newFactors = DB::table(self::TABLE_SCORING_FACTORS)
                ->where('model_id', $newModelId)
                ->orderBy('display_order')
                ->get();
            $newModel->factors = $newFactors;

            return $this->modelToArray($newModel);
        });
    }

    public function activateScoringModel(int $modelId): array
    {
        $model = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->first();

        if (!$model) {
            throw new \RuntimeException("Scoring model not found: {$modelId}");
        }

        $activeFactorsCount = DB::table(self::TABLE_SCORING_FACTORS)
            ->where('model_id', $modelId)
            ->where('is_active', true)
            ->count();

        if ($activeFactorsCount === 0) {
            throw new \RuntimeException('Cannot activate model without active factors');
        }

        DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->update([
                'status' => self::STATUS_ACTIVE,
                'updated_at' => now(),
            ]);

        return $this->modelToArray(DB::table(self::TABLE_SCORING_MODELS)->where('id', $modelId)->first());
    }

    public function archiveScoringModel(int $modelId): array
    {
        $model = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->first();

        if (!$model) {
            throw new \RuntimeException("Scoring model not found: {$modelId}");
        }

        if ($model->is_default) {
            throw new \RuntimeException('Cannot archive the default model');
        }

        DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->update([
                'status' => self::STATUS_ARCHIVED,
                'updated_at' => now(),
            ]);

        return $this->modelToArray(DB::table(self::TABLE_SCORING_MODELS)->where('id', $modelId)->first());
    }

    public function setModelAsDefault(int $modelId): array
    {
        $model = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->first();

        if (!$model) {
            throw new \RuntimeException("Scoring model not found: {$modelId}");
        }

        if ($model->status !== self::STATUS_ACTIVE) {
            throw new \RuntimeException('Only active models can be set as default');
        }

        return DB::transaction(function () use ($model, $modelId) {
            // Unset other defaults for this module
            DB::table(self::TABLE_SCORING_MODELS)
                ->where('target_module', $model->target_module)
                ->where('is_default', true)
                ->update([
                    'is_default' => false,
                    'updated_at' => now(),
                ]);

            // Set this model as default
            DB::table(self::TABLE_SCORING_MODELS)
                ->where('id', $modelId)
                ->update([
                    'is_default' => true,
                    'updated_at' => now(),
                ]);

            return $this->modelToArray(DB::table(self::TABLE_SCORING_MODELS)->where('id', $modelId)->first());
        });
    }

    // =========================================================================
    // SCORING FACTOR METHODS
    // =========================================================================

    public function addFactor(int $modelId, array $data): array
    {
        $model = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->first();

        if (!$model) {
            throw new \RuntimeException("Scoring model not found: {$modelId}");
        }

        $maxOrder = DB::table(self::TABLE_SCORING_FACTORS)
            ->where('model_id', $modelId)
            ->max('display_order') ?? 0;

        $now = now();
        $factorId = DB::table(self::TABLE_SCORING_FACTORS)->insertGetId([
            'model_id' => $modelId,
            'name' => $data['name'],
            'category' => $data['category'] ?? self::CATEGORY_DEMOGRAPHIC,
            'factor_type' => $data['factor_type'] ?? self::TYPE_FIELD_VALUE,
            'config' => json_encode($data['config'] ?? []),
            'weight' => $data['weight'] ?? 1,
            'max_points' => $data['max_points'] ?? 10,
            'is_active' => $data['is_active'] ?? true,
            'display_order' => $data['display_order'] ?? $maxOrder + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $factor = DB::table(self::TABLE_SCORING_FACTORS)->where('id', $factorId)->first();
        return $this->factorToArray($factor);
    }

    public function updateFactor(int $factorId, array $data): array
    {
        $factor = DB::table(self::TABLE_SCORING_FACTORS)
            ->where('id', $factorId)
            ->first();

        if (!$factor) {
            throw new \RuntimeException("Scoring factor not found: {$factorId}");
        }

        $updateData = [
            'name' => $data['name'] ?? $factor->name,
            'category' => $data['category'] ?? $factor->category,
            'factor_type' => $data['factor_type'] ?? $factor->factor_type,
            'config' => isset($data['config']) ? json_encode($data['config']) : $factor->config,
            'weight' => $data['weight'] ?? $factor->weight,
            'max_points' => $data['max_points'] ?? $factor->max_points,
            'is_active' => $data['is_active'] ?? $factor->is_active,
            'updated_at' => now(),
        ];

        DB::table(self::TABLE_SCORING_FACTORS)
            ->where('id', $factorId)
            ->update($updateData);

        $updatedFactor = DB::table(self::TABLE_SCORING_FACTORS)->where('id', $factorId)->first();
        return $this->factorToArray($updatedFactor);
    }

    public function deleteFactor(int $factorId): bool
    {
        $factor = DB::table(self::TABLE_SCORING_FACTORS)
            ->where('id', $factorId)
            ->first();

        if (!$factor) {
            throw new \RuntimeException("Scoring factor not found: {$factorId}");
        }

        return DB::table(self::TABLE_SCORING_FACTORS)
            ->where('id', $factorId)
            ->delete() > 0;
    }

    public function reorderFactors(int $modelId, array $factorOrder): array
    {
        return DB::transaction(function () use ($modelId, $factorOrder) {
            $now = now();
            foreach ($factorOrder as $order => $factorId) {
                DB::table(self::TABLE_SCORING_FACTORS)
                    ->where('id', $factorId)
                    ->where('model_id', $modelId)
                    ->update([
                        'display_order' => $order,
                        'updated_at' => $now,
                    ]);
            }

            return DB::table(self::TABLE_SCORING_FACTORS)
                ->where('model_id', $modelId)
                ->orderBy('display_order')
                ->get()
                ->map(fn($factor) => $this->factorToArray($factor))
                ->toArray();
        });
    }

    public function toggleFactorActive(int $factorId): array
    {
        $factor = DB::table(self::TABLE_SCORING_FACTORS)
            ->where('id', $factorId)
            ->first();

        if (!$factor) {
            throw new \RuntimeException("Scoring factor not found: {$factorId}");
        }

        DB::table(self::TABLE_SCORING_FACTORS)
            ->where('id', $factorId)
            ->update([
                'is_active' => !$factor->is_active,
                'updated_at' => now(),
            ]);

        $updatedFactor = DB::table(self::TABLE_SCORING_FACTORS)->where('id', $factorId)->first();
        return $this->factorToArray($updatedFactor);
    }

    // =========================================================================
    // LEAD SCORE METHODS
    // =========================================================================

    public function listLeadScores(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_LEAD_SCORES);

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
            $query->where('grade', $filters['grade']);
        }

        // Filter by grades (multiple)
        if (!empty($filters['grades']) && is_array($filters['grades'])) {
            $query->whereIn('grade', $filters['grades']);
        }

        // Filter high scores (A, B)
        if (!empty($filters['high_scores_only'])) {
            $query->whereIn('grade', ['A', 'B']);
        }

        // Filter low scores (D, F)
        if (!empty($filters['low_scores_only'])) {
            $query->whereIn('grade', ['D', 'F']);
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
        $items = $query->forPage($page, $perPage)
            ->get()
            ->map(function($score) {
                // Load scoring model
                $scoringModel = DB::table(self::TABLE_SCORING_MODELS)
                    ->where('id', $score->model_id)
                    ->first();
                $score->scoringModel = $scoringModel;

                return $this->leadScoreToArray($score);
            })
            ->toArray();

        return PaginatedResult::create($items, $total, $perPage, $page);
    }

    public function getLeadScore(int $scoreId): ?array
    {
        $score = DB::table(self::TABLE_LEAD_SCORES)
            ->where('id', $scoreId)
            ->first();

        if (!$score) {
            return null;
        }

        // Load scoring model
        $scoringModel = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $score->model_id)
            ->first();
        $score->scoringModel = $scoringModel;

        // Load history (limit 30)
        $history = DB::table(self::TABLE_LEAD_SCORE_HISTORY)
            ->where('lead_score_id', $scoreId)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();
        $score->history = $history;

        return $this->leadScoreToArray($score);
    }

    public function getScoreForRecord(string $module, int $recordId, ?int $modelId = null): ?array
    {
        $query = DB::table(self::TABLE_LEAD_SCORES)
            ->where('record_module', $module)
            ->where('record_id', $recordId);

        if ($modelId) {
            $query->where('model_id', $modelId);
        } else {
            // Get score from default model
            $defaultModel = DB::table(self::TABLE_SCORING_MODELS)
                ->where('target_module', $module)
                ->where('is_default', true)
                ->where('status', self::STATUS_ACTIVE)
                ->first();

            if ($defaultModel) {
                $query->where('model_id', $defaultModel->id);
            }
        }

        $score = $query->first();

        if (!$score) {
            return null;
        }

        // Load scoring model
        $scoringModel = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $score->model_id)
            ->first();
        $score->scoringModel = $scoringModel;

        // Load history
        $history = DB::table(self::TABLE_LEAD_SCORE_HISTORY)
            ->where('lead_score_id', $score->id)
            ->orderByDesc('created_at')
            ->get();
        $score->history = $history;

        return $this->leadScoreToArray($score);
    }

    public function calculateScore(string $module, int $recordId, ?int $modelId = null): array
    {
        // Get the model
        $model = $modelId
            ? DB::table(self::TABLE_SCORING_MODELS)->where('id', $modelId)->first()
            : DB::table(self::TABLE_SCORING_MODELS)
                ->where('target_module', $module)
                ->where('is_default', true)
                ->where('status', self::STATUS_ACTIVE)
                ->first();

        if (!$model) {
            throw new \RuntimeException("No scoring model found for module: {$module}");
        }

        if ($model->status !== self::STATUS_ACTIVE) {
            throw new \RuntimeException('Scoring model is not active');
        }

        // Get the record
        $moduleId = DB::table(self::TABLE_MODULES)
            ->where('api_name', $module)
            ->value('id');

        if (!$moduleId) {
            throw new \RuntimeException("Module not found: {$module}");
        }

        $record = DB::table(self::TABLE_MODULE_RECORDS)
            ->where('module_id', $moduleId)
            ->where('id', $recordId)
            ->first();

        if (!$record) {
            throw new \RuntimeException("Record not found: {$recordId}");
        }

        // NOTE: The original code calls $model->calculateScore() which is a model method.
        // Since we're using Query Builder, we need to implement the scoring logic here
        // or delegate to a domain service. For now, throwing an exception to indicate
        // this needs to be implemented outside the repository.
        throw new \RuntimeException('Score calculation logic needs to be moved to a domain service');

        // The rest of the code would look like this once we have the result:
        /*
        $now = now();

        // Check if score already exists
        $existingScore = DB::table(self::TABLE_LEAD_SCORES)
            ->where('model_id', $model->id)
            ->where('record_module', $module)
            ->where('record_id', $recordId)
            ->first();

        if ($existingScore) {
            // Update existing score
            DB::table(self::TABLE_LEAD_SCORES)
                ->where('id', $existingScore->id)
                ->update([
                    'score' => $result['score'],
                    'grade' => $result['grade'],
                    'factor_breakdown' => json_encode($result['breakdown']),
                    'explanation' => json_encode($result['explanations']),
                    'calculated_at' => $now,
                    'updated_at' => $now,
                ]);

            // Record history
            DB::table(self::TABLE_LEAD_SCORE_HISTORY)->insert([
                'lead_score_id' => $existingScore->id,
                'score' => $result['score'],
                'grade' => $result['grade'],
                'change_reason' => 'Score recalculated',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $leadScoreId = $existingScore->id;
        } else {
            // Create new score
            $leadScoreId = DB::table(self::TABLE_LEAD_SCORES)->insertGetId([
                'model_id' => $model->id,
                'record_module' => $module,
                'record_id' => $recordId,
                'score' => $result['score'],
                'grade' => $result['grade'],
                'factor_breakdown' => json_encode($result['breakdown']),
                'explanation' => json_encode($result['explanations']),
                'calculated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Load the score with model
        $leadScore = DB::table(self::TABLE_LEAD_SCORES)->where('id', $leadScoreId)->first();
        $scoringModel = DB::table(self::TABLE_SCORING_MODELS)->where('id', $model->id)->first();
        $leadScore->scoringModel = $scoringModel;

        return $this->leadScoreToArray($leadScore);
        */
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
        $model = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->first();

        if (!$model) {
            throw new \RuntimeException("Scoring model not found: {$modelId}");
        }

        $scores = DB::table(self::TABLE_LEAD_SCORES)
            ->where('model_id', $modelId)
            ->get();

        $count = 0;

        foreach ($scores as $score) {
            try {
                $this->calculateScore($score->record_module, $score->record_id, $score->model_id);
                $count++;
            } catch (\Exception $e) {
                // Log error but continue
            }
        }

        return $count;
    }

    public function getScoreHistory(int $scoreId, int $limit = 30): array
    {
        return DB::table(self::TABLE_LEAD_SCORE_HISTORY)
            ->where('lead_score_id', $scoreId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($history) => [
                'id' => $history->id,
                'lead_score_id' => $history->lead_score_id,
                'score' => $history->score,
                'grade' => $history->grade,
                'change_reason' => $history->change_reason,
                'created_at' => isset($history->created_at) ? Carbon::parse($history->created_at)->toISOString() : null,
            ])
            ->toArray();
    }

    public function getScoreTrend(int $scoreId, int $days = 30): array
    {
        $score = DB::table(self::TABLE_LEAD_SCORES)
            ->where('id', $scoreId)
            ->first();

        if (!$score) {
            throw new \RuntimeException("Lead score not found: {$scoreId}");
        }

        // NOTE: Original code calls $score->getTrend($days) which is a model method
        // This needs to be implemented here or moved to a domain service
        $startDate = Carbon::now()->subDays($days);

        $history = DB::table(self::TABLE_LEAD_SCORE_HISTORY)
            ->where('lead_score_id', $scoreId)
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at')
            ->get();

        return $history->map(fn($h) => [
            'date' => isset($h->created_at) ? Carbon::parse($h->created_at)->toDateString() : null,
            'score' => $h->score,
            'grade' => $h->grade,
        ])->toArray();
    }

    // =========================================================================
    // ANALYTICS METHODS
    // =========================================================================

    public function getModelStats(int $modelId): array
    {
        $model = DB::table(self::TABLE_SCORING_MODELS)
            ->where('id', $modelId)
            ->first();

        if (!$model) {
            throw new \RuntimeException("Scoring model not found: {$modelId}");
        }

        $scores = DB::table(self::TABLE_LEAD_SCORES)
            ->where('model_id', $modelId)
            ->get();

        // Calculate grade distribution
        $gradeDistribution = [
            'A' => 0,
            'B' => 0,
            'C' => 0,
            'D' => 0,
            'F' => 0,
        ];

        $scoreValues = [];
        $highScoreCount = 0;
        $lowScoreCount = 0;

        foreach ($scores as $score) {
            $scoreValues[] = $score->score;
            if (isset($gradeDistribution[$score->grade])) {
                $gradeDistribution[$score->grade]++;
            }
            if (in_array($score->grade, ['A', 'B'])) {
                $highScoreCount++;
            }
            if (in_array($score->grade, ['D', 'F'])) {
                $lowScoreCount++;
            }
        }

        $avgScore = count($scoreValues) > 0 ? array_sum($scoreValues) / count($scoreValues) : 0;

        $factorCount = DB::table(self::TABLE_SCORING_FACTORS)
            ->where('model_id', $modelId)
            ->where('is_active', true)
            ->count();

        return [
            'model_id' => $modelId,
            'total_scored' => $scores->count(),
            'avg_score' => round($avgScore, 1),
            'median_score' => $this->calculateMedian($scoreValues),
            'grade_distribution' => $gradeDistribution,
            'high_score_count' => $highScoreCount,
            'low_score_count' => $lowScoreCount,
            'factor_count' => $factorCount,
        ];
    }

    public function getTopScoredRecords(string $module, int $limit = 10, ?int $modelId = null): array
    {
        $query = DB::table(self::TABLE_LEAD_SCORES)
            ->where('record_module', $module);

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
        $query = DB::table(self::TABLE_LEAD_SCORES)
            ->where('record_module', $module);

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
        $query = DB::table(self::TABLE_LEAD_SCORES)
            ->where('record_module', $module);

        if ($modelId) {
            $query->where('model_id', $modelId);
        }

        $scores = $query->get();

        $analysis = [];
        $grades = ['A', 'B', 'C', 'D', 'F'];

        // Get module ID for record lookup
        $moduleId = DB::table(self::TABLE_MODULES)
            ->where('api_name', $module)
            ->value('id');

        foreach ($grades as $grade) {
            $gradeScores = $scores->where('grade', $grade);
            $total = $gradeScores->count();
            $converted = 0;

            foreach ($gradeScores as $score) {
                // Get the record
                $record = DB::table(self::TABLE_MODULE_RECORDS)
                    ->where('module_id', $moduleId)
                    ->where('id', $score->record_id)
                    ->first();

                if ($record) {
                    $data = json_decode($record->data, true) ?? [];
                    if (!empty($data[$conversionField])) {
                        $converted++;
                    }
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

        $changes = DB::table(self::TABLE_LEAD_SCORE_HISTORY . ' as h')
            ->join(self::TABLE_LEAD_SCORES . ' as s', 'h.lead_score_id', '=', 's.id')
            ->where('s.model_id', $modelId)
            ->where('h.created_at', '>=', $startDate)
            ->selectRaw("DATE(h.created_at) as date, AVG(h.score) as avg_score, COUNT(*) as count")
            ->groupByRaw('DATE(h.created_at)')
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

        $query = DB::table(self::TABLE_LEAD_SCORES)
            ->where('record_module', $module);

        if ($modelId) {
            $query->where('model_id', $modelId);
        }

        // Get scores that have history in the date range
        $scoreIds = DB::table(self::TABLE_LEAD_SCORE_HISTORY)
            ->where('created_at', '>=', $startDate)
            ->distinct()
            ->pluck('lead_score_id');

        if ($scoreIds->isEmpty()) {
            return [];
        }

        $scores = $query->whereIn('id', $scoreIds)->get();

        $improvements = [];

        foreach ($scores as $score) {
            $oldestHistory = DB::table(self::TABLE_LEAD_SCORE_HISTORY)
                ->where('lead_score_id', $score->id)
                ->where('created_at', '>=', $startDate)
                ->orderBy('created_at')
                ->first();

            if (!$oldestHistory) {
                continue;
            }

            $improvement = $score->score - $oldestHistory->score;

            $improvements[] = [
                'record_id' => $score->record_id,
                'record_module' => $score->record_module,
                'previous_score' => $oldestHistory->score,
                'current_score' => $score->score,
                'improvement' => $improvement,
                'previous_grade' => $oldestHistory->grade,
                'current_grade' => $score->grade,
            ];
        }

        // Sort by improvement descending
        usort($improvements, fn($a, $b) => $b['improvement'] <=> $a['improvement']);

        return $improvements;
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function toArray(stdClass $obj): array
    {
        return json_decode(json_encode($obj), true);
    }

    private function modelToArray(stdClass $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description ?? null,
            'target_module' => $model->target_module,
            'status' => $model->status,
            'model_type' => $model->model_type,
            'features' => is_string($model->features ?? null) ? json_decode($model->features, true) : ($model->features ?? []),
            'weights' => is_string($model->weights ?? null) ? json_decode($model->weights, true) : ($model->weights ?? []),
            'accuracy' => $model->accuracy ?? null,
            'training_records' => $model->training_records ?? null,
            'trained_at' => isset($model->trained_at) ? Carbon::parse($model->trained_at)->toISOString() : null,
            'is_default' => $model->is_default ?? false,
            'factors_count' => $model->factors_count ?? null,
            'scores_count' => $model->scores_count ?? null,
            'factors' => isset($model->factors)
                ? collect($model->factors)->map(fn($factor) => $this->factorToArray($factor))->toArray()
                : null,
            'created_at' => isset($model->created_at) ? Carbon::parse($model->created_at)->toISOString() : null,
            'updated_at' => isset($model->updated_at) ? Carbon::parse($model->updated_at)->toISOString() : null,
        ];
    }

    private function factorToArray(stdClass $factor): array
    {
        return [
            'id' => $factor->id,
            'model_id' => $factor->model_id,
            'name' => $factor->name,
            'category' => $factor->category,
            'factor_type' => $factor->factor_type,
            'config' => is_string($factor->config ?? null) ? json_decode($factor->config, true) : ($factor->config ?? []),
            'weight' => $factor->weight,
            'max_points' => $factor->max_points,
            'is_active' => $factor->is_active ?? false,
            'display_order' => $factor->display_order ?? 0,
            'created_at' => isset($factor->created_at) ? Carbon::parse($factor->created_at)->toISOString() : null,
            'updated_at' => isset($factor->updated_at) ? Carbon::parse($factor->updated_at)->toISOString() : null,
        ];
    }

    private function leadScoreToArray(stdClass $score): array
    {
        return [
            'id' => $score->id,
            'model_id' => $score->model_id,
            'record_module' => $score->record_module,
            'record_id' => $score->record_id,
            'score' => $score->score,
            'grade' => $score->grade,
            'factor_breakdown' => is_string($score->factor_breakdown ?? null) ? json_decode($score->factor_breakdown, true) : ($score->factor_breakdown ?? []),
            'explanation' => is_string($score->explanation ?? null) ? json_decode($score->explanation, true) : ($score->explanation ?? []),
            'conversion_probability' => $score->conversion_probability ?? null,
            'calculated_at' => isset($score->calculated_at) ? Carbon::parse($score->calculated_at)->toISOString() : null,
            'scoring_model' => isset($score->scoringModel) && $score->scoringModel
                ? $this->modelToArray($score->scoringModel)
                : null,
            'history' => isset($score->history)
                ? collect($score->history)->map(fn($h) => [
                    'id' => $h->id,
                    'lead_score_id' => $h->lead_score_id,
                    'score' => $h->score,
                    'grade' => $h->grade,
                    'change_reason' => $h->change_reason ?? null,
                    'created_at' => isset($h->created_at) ? Carbon::parse($h->created_at)->toISOString() : null,
                ])->toArray()
                : null,
            'created_at' => isset($score->created_at) ? Carbon::parse($score->created_at)->toISOString() : null,
            'updated_at' => isset($score->updated_at) ? Carbon::parse($score->updated_at)->toISOString() : null,
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
