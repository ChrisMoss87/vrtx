<?php

declare(strict_types=1);

namespace App\Application\Services\Duplicate;

use App\Domain\Duplicate\Repositories\DuplicateCandidateRepositoryInterface;
use App\Models\DuplicateCandidate;
use App\Models\DuplicateRule;
use App\Models\ModuleRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DuplicateApplicationService
{
    public function __construct(
        private DuplicateCandidateRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - DUPLICATE CANDIDATES
    // =========================================================================

    /**
     * List duplicate candidates with filtering and pagination.
     */
    public function listCandidates(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = DuplicateCandidate::query()
            ->with(['module:id,name,api_name', 'recordA', 'recordB', 'reviewer:id,name']);

        // Filter by module
        if (!empty($filters['module_id'])) {
            $query->forModule($filters['module_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            // Default to pending only
            $query->pending();
        }

        // Filter by minimum score
        if (!empty($filters['min_score'])) {
            $query->where('match_score', '>=', $filters['min_score']);
        }

        // Filter by record ID
        if (!empty($filters['record_id'])) {
            $recordId = $filters['record_id'];
            $query->where(function ($q) use ($recordId) {
                $q->where('record_id_a', $recordId)
                    ->orWhere('record_id_b', $recordId);
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'match_score';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single candidate by ID.
     */
    public function getCandidate(int $id): ?DuplicateCandidate
    {
        return DuplicateCandidate::with(['module', 'recordA', 'recordB', 'reviewer:id,name'])->find($id);
    }

    /**
     * Get candidates for a specific record.
     */
    public function getCandidatesForRecord(int $recordId): Collection
    {
        return DuplicateCandidate::pending()
            ->where(function ($q) use ($recordId) {
                $q->where('record_id_a', $recordId)
                    ->orWhere('record_id_b', $recordId);
            })
            ->with(['recordA', 'recordB'])
            ->highestMatch()
            ->get();
    }

    /**
     * Get duplicate statistics for a module.
     */
    public function getModuleStats(int $moduleId): array
    {
        $candidates = DuplicateCandidate::forModule($moduleId);

        $pending = (clone $candidates)->pending()->count();
        $merged = (clone $candidates)->where('status', DuplicateCandidate::STATUS_MERGED)->count();
        $dismissed = (clone $candidates)->where('status', DuplicateCandidate::STATUS_DISMISSED)->count();

        $avgScore = (clone $candidates)->pending()->avg('match_score') ?? 0;
        $highConfidence = (clone $candidates)->pending()->where('match_score', '>=', 0.9)->count();

        return [
            'module_id' => $moduleId,
            'pending' => $pending,
            'merged' => $merged,
            'dismissed' => $dismissed,
            'total_processed' => $merged + $dismissed,
            'average_score' => round($avgScore, 2),
            'high_confidence' => $highConfidence,
        ];
    }

    /**
     * Get overall duplicate statistics.
     */
    public function getOverallStats(): array
    {
        $pending = DuplicateCandidate::pending()->count();
        $merged = DuplicateCandidate::where('status', DuplicateCandidate::STATUS_MERGED)->count();
        $dismissed = DuplicateCandidate::where('status', DuplicateCandidate::STATUS_DISMISSED)->count();

        $byModule = DuplicateCandidate::pending()
            ->selectRaw('module_id, COUNT(*) as count')
            ->groupBy('module_id')
            ->with('module:id,name')
            ->get();

        return [
            'pending' => $pending,
            'merged' => $merged,
            'dismissed' => $dismissed,
            'total' => $pending + $merged + $dismissed,
            'by_module' => $byModule,
        ];
    }

    // =========================================================================
    // COMMAND USE CASES - DUPLICATE CANDIDATES
    // =========================================================================

    /**
     * Merge two duplicate records.
     */
    public function mergeRecords(int $candidateId, int $masterRecordId, array $mergeConfig = []): array
    {
        $candidate = DuplicateCandidate::findOrFail($candidateId);

        // Determine which is master and which is duplicate
        $duplicateRecordId = $candidate->record_id_a === $masterRecordId
            ? $candidate->record_id_b
            : $candidate->record_id_a;

        $masterRecord = ModuleRecord::findOrFail($masterRecordId);
        $duplicateRecord = ModuleRecord::findOrFail($duplicateRecordId);

        return DB::transaction(function () use ($candidate, $masterRecord, $duplicateRecord, $mergeConfig) {
            // Merge field values based on config
            $mergedData = $this->mergeFieldValues(
                $masterRecord->data ?? [],
                $duplicateRecord->data ?? [],
                $mergeConfig
            );

            // Update master record with merged data
            $masterRecord->update(['data' => $mergedData]);

            // Transfer related records (activities, notes, etc.)
            $this->transferRelatedRecords($duplicateRecord->id, $masterRecord->id);

            // Soft delete the duplicate
            $duplicateRecord->delete();

            // Mark candidate as merged
            $candidate->markAsMerged(Auth::id());

            // Also dismiss any other candidates involving the duplicate record
            DuplicateCandidate::pending()
                ->where(function ($q) use ($duplicateRecord) {
                    $q->where('record_id_a', $duplicateRecord->id)
                        ->orWhere('record_id_b', $duplicateRecord->id);
                })
                ->update([
                    'status' => DuplicateCandidate::STATUS_DISMISSED,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                    'dismiss_reason' => 'Record was merged in another duplicate set',
                ]);

            return [
                'master_record_id' => $masterRecord->id,
                'deleted_record_id' => $duplicateRecord->id,
                'merged_data' => $mergedData,
            ];
        });
    }

    /**
     * Dismiss a duplicate candidate.
     */
    public function dismissCandidate(int $candidateId, ?string $reason = null): DuplicateCandidate
    {
        $candidate = DuplicateCandidate::findOrFail($candidateId);
        $candidate->markAsDismissed(Auth::id(), $reason);
        return $candidate->fresh();
    }

    /**
     * Bulk dismiss candidates.
     */
    public function bulkDismiss(array $candidateIds, ?string $reason = null): int
    {
        return DuplicateCandidate::whereIn('id', $candidateIds)
            ->pending()
            ->update([
                'status' => DuplicateCandidate::STATUS_DISMISSED,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'dismiss_reason' => $reason,
            ]);
    }

    /**
     * Create a duplicate candidate manually.
     */
    public function createCandidate(int $moduleId, int $recordIdA, int $recordIdB, float $score = 1.0, array $matchedRules = []): DuplicateCandidate
    {
        // Ensure consistent ordering
        if ($recordIdA > $recordIdB) {
            [$recordIdA, $recordIdB] = [$recordIdB, $recordIdA];
        }

        // Check if candidate already exists
        $existing = DuplicateCandidate::where('module_id', $moduleId)
            ->where('record_id_a', $recordIdA)
            ->where('record_id_b', $recordIdB)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DuplicateCandidate::create([
            'module_id' => $moduleId,
            'record_id_a' => $recordIdA,
            'record_id_b' => $recordIdB,
            'match_score' => $score,
            'matched_rules' => $matchedRules,
            'status' => DuplicateCandidate::STATUS_PENDING,
        ]);
    }

    // =========================================================================
    // QUERY USE CASES - DUPLICATE RULES
    // =========================================================================

    /**
     * List duplicate rules.
     */
    public function listRules(array $filters = []): Collection
    {
        $query = DuplicateRule::query()->with(['module:id,name,api_name', 'creator:id,name']);

        if (!empty($filters['module_id'])) {
            $query->forModule($filters['module_id']);
        }

        if (isset($filters['is_active'])) {
            if ($filters['is_active']) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        return $query->ordered()->get();
    }

    /**
     * Get a rule by ID.
     */
    public function getRule(int $id): ?DuplicateRule
    {
        return DuplicateRule::with(['module', 'creator:id,name'])->find($id);
    }

    // =========================================================================
    // COMMAND USE CASES - DUPLICATE RULES
    // =========================================================================

    /**
     * Create a duplicate rule.
     */
    public function createRule(array $data): DuplicateRule
    {
        return DuplicateRule::create([
            'module_id' => $data['module_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'action' => $data['action'] ?? DuplicateRule::ACTION_WARN,
            'conditions' => $data['conditions'] ?? [],
            'priority' => $data['priority'] ?? 0,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Update a duplicate rule.
     */
    public function updateRule(int $id, array $data): DuplicateRule
    {
        $rule = DuplicateRule::findOrFail($id);

        $rule->update([
            'name' => $data['name'] ?? $rule->name,
            'description' => $data['description'] ?? $rule->description,
            'is_active' => $data['is_active'] ?? $rule->is_active,
            'action' => $data['action'] ?? $rule->action,
            'conditions' => $data['conditions'] ?? $rule->conditions,
            'priority' => $data['priority'] ?? $rule->priority,
        ]);

        return $rule->fresh();
    }

    /**
     * Delete a duplicate rule.
     */
    public function deleteRule(int $id): bool
    {
        $rule = DuplicateRule::findOrFail($id);
        return $rule->delete();
    }

    /**
     * Toggle rule active status.
     */
    public function toggleRuleActive(int $id): DuplicateRule
    {
        $rule = DuplicateRule::findOrFail($id);
        $rule->update(['is_active' => !$rule->is_active]);
        return $rule->fresh();
    }

    // =========================================================================
    // DUPLICATE DETECTION
    // =========================================================================

    /**
     * Check a record for duplicates.
     */
    public function checkForDuplicates(ModuleRecord $record): Collection
    {
        $rules = DuplicateRule::forModule($record->module_id)
            ->active()
            ->ordered()
            ->get();

        $duplicates = collect();

        foreach ($rules as $rule) {
            $matches = $this->findMatchesForRule($record, $rule);
            $duplicates = $duplicates->merge($matches);
        }

        // Remove duplicates and sort by score
        return $duplicates->unique('record_id')->sortByDesc('score')->values();
    }

    /**
     * Find matches for a specific rule.
     */
    public function findMatchesForRule(ModuleRecord $record, DuplicateRule $rule): Collection
    {
        $matches = collect();
        $conditions = $rule->conditions ?? [];

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $matchType = $condition['match_type'] ?? DuplicateRule::MATCH_EXACT;
            $weight = $condition['weight'] ?? 1.0;

            if (!$field) continue;

            $fieldValue = $record->data[$field] ?? null;
            if (!$fieldValue) continue;

            $query = ModuleRecord::where('module_id', $record->module_id)
                ->where('id', '!=', $record->id)
                ->whereNull('deleted_at');

            $matchingRecords = $this->applyMatchCondition($query, $field, $fieldValue, $matchType);

            foreach ($matchingRecords as $match) {
                $score = $this->calculateMatchScore($record, $match, $conditions);

                if (!$matches->has($match->id)) {
                    $matches->put($match->id, [
                        'record_id' => $match->id,
                        'record' => $match,
                        'score' => $score,
                        'matched_fields' => [$field],
                        'rule_id' => $rule->id,
                    ]);
                } else {
                    $existing = $matches->get($match->id);
                    $existing['matched_fields'][] = $field;
                    $existing['score'] = max($existing['score'], $score);
                    $matches->put($match->id, $existing);
                }
            }
        }

        return $matches->values();
    }

    /**
     * Run duplicate scan for a module.
     */
    public function runDuplicateScan(int $moduleId, ?int $limit = null): array
    {
        $rules = DuplicateRule::forModule($moduleId)->active()->ordered()->get();

        if ($rules->isEmpty()) {
            return ['scanned' => 0, 'duplicates_found' => 0];
        }

        $query = ModuleRecord::where('module_id', $moduleId)->whereNull('deleted_at');

        if ($limit) {
            $query->limit($limit);
        }

        $records = $query->get();
        $scanned = 0;
        $duplicatesFound = 0;

        foreach ($records as $record) {
            $duplicates = $this->checkForDuplicates($record);
            $scanned++;

            foreach ($duplicates as $duplicate) {
                if ($duplicate['score'] >= 0.7) { // Threshold
                    $candidate = $this->createCandidate(
                        $moduleId,
                        $record->id,
                        $duplicate['record_id'],
                        $duplicate['score'],
                        $duplicate['matched_fields'] ?? []
                    );

                    if ($candidate->wasRecentlyCreated) {
                        $duplicatesFound++;
                    }
                }
            }
        }

        return [
            'scanned' => $scanned,
            'duplicates_found' => $duplicatesFound,
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Merge field values from two records.
     */
    private function mergeFieldValues(array $masterData, array $duplicateData, array $config): array
    {
        $merged = $masterData;

        foreach ($duplicateData as $field => $value) {
            if (!isset($merged[$field]) || $merged[$field] === null || $merged[$field] === '') {
                // Master is empty, use duplicate value
                $merged[$field] = $value;
            } elseif (isset($config[$field])) {
                // Use config to determine which value to use
                $source = $config[$field];
                if ($source === 'duplicate') {
                    $merged[$field] = $value;
                } elseif ($source === 'concat') {
                    $merged[$field] = $masterData[$field] . '; ' . $value;
                }
                // 'master' or default keeps master value
            }
        }

        return $merged;
    }

    /**
     * Transfer related records from duplicate to master.
     */
    private function transferRelatedRecords(int $fromRecordId, int $toRecordId): void
    {
        // Transfer activities
        DB::table('activities')
            ->where('subject_type', ModuleRecord::class)
            ->where('subject_id', $fromRecordId)
            ->update(['subject_id' => $toRecordId]);

        // Transfer notes
        DB::table('notes')
            ->where('notable_type', ModuleRecord::class)
            ->where('notable_id', $fromRecordId)
            ->update(['notable_id' => $toRecordId]);

        // Transfer attachments
        DB::table('attachments')
            ->where('attachable_type', ModuleRecord::class)
            ->where('attachable_id', $fromRecordId)
            ->update(['attachable_id' => $toRecordId]);
    }

    /**
     * Apply match condition to query.
     */
    private function applyMatchCondition($query, string $field, mixed $value, string $matchType): Collection
    {
        return match ($matchType) {
            DuplicateRule::MATCH_EXACT => $query->whereRaw("data->>? = ?", [$field, $value])->get(),
            DuplicateRule::MATCH_FUZZY => $query->whereRaw("data->>? ILIKE ?", [$field, "%{$value}%"])->get(),
            DuplicateRule::MATCH_PHONETIC => $this->findPhoneticMatches($query, $field, $value),
            DuplicateRule::MATCH_EMAIL_DOMAIN => $this->findEmailDomainMatches($query, $field, $value),
            default => $query->whereRaw("data->>? = ?", [$field, $value])->get(),
        };
    }

    /**
     * Find phonetic matches (simplified Soundex).
     */
    private function findPhoneticMatches($query, string $field, string $value): Collection
    {
        $soundex = soundex($value);
        return $query->get()->filter(function ($record) use ($field, $soundex) {
            $recordValue = $record->data[$field] ?? '';
            return soundex($recordValue) === $soundex;
        });
    }

    /**
     * Find email domain matches.
     */
    private function findEmailDomainMatches($query, string $field, string $value): Collection
    {
        $domain = substr(strrchr($value, "@"), 1);
        if (!$domain) return collect();

        return $query->whereRaw("data->>? ILIKE ?", [$field, "%@{$domain}"])->get();
    }

    /**
     * Calculate match score between two records.
     */
    private function calculateMatchScore(ModuleRecord $recordA, ModuleRecord $recordB, array $conditions): float
    {
        $totalWeight = 0;
        $matchedWeight = 0;

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $weight = $condition['weight'] ?? 1.0;

            if (!$field) continue;

            $totalWeight += $weight;

            $valueA = $recordA->data[$field] ?? null;
            $valueB = $recordB->data[$field] ?? null;

            if ($valueA && $valueB) {
                $similarity = $this->calculateFieldSimilarity($valueA, $valueB, $condition['match_type'] ?? 'exact');
                $matchedWeight += $weight * $similarity;
            }
        }

        return $totalWeight > 0 ? $matchedWeight / $totalWeight : 0;
    }

    /**
     * Calculate similarity between two field values.
     */
    private function calculateFieldSimilarity(mixed $valueA, mixed $valueB, string $matchType): float
    {
        $stringA = strtolower(trim((string) $valueA));
        $stringB = strtolower(trim((string) $valueB));

        if ($stringA === $stringB) {
            return 1.0;
        }

        return match ($matchType) {
            DuplicateRule::MATCH_FUZZY => 1 - (levenshtein($stringA, $stringB) / max(strlen($stringA), strlen($stringB), 1)),
            DuplicateRule::MATCH_PHONETIC => soundex($stringA) === soundex($stringB) ? 0.9 : 0,
            default => 0,
        };
    }
}
