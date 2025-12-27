<?php

declare(strict_types=1);

namespace App\Application\Services\Duplicate;

use App\Domain\Duplicate\Repositories\DuplicateCandidateRepositoryInterface;
use App\Domain\Duplicate\Repositories\DuplicateRuleRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DuplicateApplicationService
{
    private const MATCH_EXACT = 'exact';
    private const MATCH_FUZZY = 'fuzzy';
    private const MATCH_PHONETIC = 'phonetic';
    private const MATCH_EMAIL_DOMAIN = 'email_domain';

    private const STATUS_PENDING = 'pending';
    private const STATUS_MERGED = 'merged';
    private const STATUS_DISMISSED = 'dismissed';

    private const ACTION_WARN = 'warn';

    public function __construct(
        private DuplicateCandidateRepositoryInterface $candidateRepository,
        private DuplicateRuleRepositoryInterface $ruleRepository,
        private ModuleRecordRepositoryInterface $recordRepository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - DUPLICATE CANDIDATES
    // =========================================================================

    /**
     * List duplicate candidates with filtering and pagination.
     */
    public function listCandidates(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        return $this->candidateRepository->listCandidates($filters, $perPage, $page);
    }

    /**
     * Get a single candidate by ID.
     */
    public function getCandidate(int $id): ?array
    {
        return $this->candidateRepository->findByIdAsArray($id);
    }

    /**
     * Get candidates for a specific record.
     */
    public function getCandidatesForRecord(int $recordId): array
    {
        return $this->candidateRepository->getCandidatesForRecord($recordId);
    }

    /**
     * Get duplicate statistics for a module.
     */
    public function getModuleStats(int $moduleId): array
    {
        $pending = $this->candidateRepository->countPendingForModule($moduleId);
        $merged = $this->candidateRepository->countByStatus($moduleId, self::STATUS_MERGED);
        $dismissed = $this->candidateRepository->countByStatus($moduleId, self::STATUS_DISMISSED);
        $avgScore = $this->candidateRepository->getAverageScore($moduleId);
        $highConfidence = $this->candidateRepository->countHighConfidence($moduleId, 0.9);

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
        $allCandidates = $this->candidateRepository->findAll();

        $pending = count(array_filter($allCandidates, fn($c) => $c['status'] === self::STATUS_PENDING));
        $merged = count(array_filter($allCandidates, fn($c) => $c['status'] === self::STATUS_MERGED));
        $dismissed = count(array_filter($allCandidates, fn($c) => $c['status'] === self::STATUS_DISMISSED));

        $byModule = $this->candidateRepository->countByModule();

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
        $candidate = $this->candidateRepository->findByIdAsArray($candidateId);

        if (!$candidate) {
            throw new \RuntimeException("Candidate not found: {$candidateId}");
        }

        $duplicateRecordId = $candidate['record_id_a'] === $masterRecordId
            ? $candidate['record_id_b']
            : $candidate['record_id_a'];

        $moduleId = $candidate['module_id'];

        $masterRecord = $this->recordRepository->findByIdAsArray($masterRecordId);
        $duplicateRecord = $this->recordRepository->findByIdAsArray($duplicateRecordId);

        if (!$masterRecord || !$duplicateRecord) {
            throw new \RuntimeException("Records not found");
        }

        return DB::transaction(function () use ($candidateId, $masterRecord, $duplicateRecord, $mergeConfig, $duplicateRecordId, $masterRecordId, $moduleId) {
            $mergedData = $this->mergeFieldValues(
                $masterRecord['data'] ?? [],
                $duplicateRecord['data'] ?? [],
                $mergeConfig
            );

            $this->recordRepository->update($masterRecordId, ['data' => $mergedData]);

            $this->transferRelatedRecords($duplicateRecordId, $masterRecordId);

            $this->recordRepository->delete($moduleId, $duplicateRecordId);

            $userId = $this->authContext->userId();
            if (!$userId) {
                throw new \RuntimeException("User not authenticated");
            }

            $this->candidateRepository->markAsMerged($candidateId, $userId);

            $relatedCandidates = $this->candidateRepository->getCandidatesForRecord($duplicateRecordId);

            $relatedIds = array_column(
                array_filter($relatedCandidates, fn($c) => $c['status'] === self::STATUS_PENDING),
                'id'
            );

            if (!empty($relatedIds)) {
                $this->candidateRepository->bulkUpdate($relatedIds, [
                    'status' => self::STATUS_DISMISSED,
                    'reviewed_by' => $userId,
                    'reviewed_at' => now(),
                    'dismiss_reason' => 'Record was merged in another duplicate set',
                ]);
            }

            return [
                'master_record_id' => $masterRecordId,
                'deleted_record_id' => $duplicateRecordId,
                'merged_data' => $mergedData,
            ];
        });
    }

    /**
     * Dismiss a duplicate candidate.
     */
    public function dismissCandidate(int $candidateId, ?string $reason = null): ?array
    {
        $userId = $this->authContext->userId();
        if (!$userId) {
            throw new \RuntimeException("User not authenticated");
        }

        $this->candidateRepository->markAsDismissed($candidateId, $userId, $reason);
        return $this->candidateRepository->findByIdAsArray($candidateId);
    }

    /**
     * Bulk dismiss candidates.
     */
    public function bulkDismiss(array $candidateIds, ?string $reason = null): int
    {
        $userId = $this->authContext->userId();
        if (!$userId) {
            throw new \RuntimeException("User not authenticated");
        }

        $pendingIds = [];
        foreach ($candidateIds as $id) {
            $candidate = $this->candidateRepository->findByIdAsArray($id);
            if ($candidate && $candidate['status'] === self::STATUS_PENDING) {
                $pendingIds[] = $id;
            }
        }

        if (empty($pendingIds)) {
            return 0;
        }

        return $this->candidateRepository->bulkUpdate($pendingIds, [
            'status' => self::STATUS_DISMISSED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'dismiss_reason' => $reason,
        ]);
    }

    /**
     * Create a duplicate candidate manually.
     */
    public function createCandidate(int $moduleId, int $recordIdA, int $recordIdB, float $score = 1.0, array $matchedRules = []): array
    {
        if ($recordIdA > $recordIdB) {
            [$recordIdA, $recordIdB] = [$recordIdB, $recordIdA];
        }

        $existing = $this->candidateRepository->findByRecordPair($moduleId, $recordIdA, $recordIdB);

        if ($existing) {
            return $existing;
        }

        return $this->candidateRepository->create([
            'module_id' => $moduleId,
            'record_id_a' => $recordIdA,
            'record_id_b' => $recordIdB,
            'match_score' => $score,
            'matched_rules' => $matchedRules,
            'status' => self::STATUS_PENDING,
        ]);
    }

    // =========================================================================
    // QUERY USE CASES - DUPLICATE RULES
    // =========================================================================

    /**
     * List duplicate rules.
     */
    public function listRules(array $filters = []): array
    {
        return $this->ruleRepository->listRules($filters);
    }

    /**
     * Get a rule by ID.
     */
    public function getRule(int $id): ?array
    {
        return $this->ruleRepository->findById($id);
    }

    // =========================================================================
    // COMMAND USE CASES - DUPLICATE RULES
    // =========================================================================

    /**
     * Create a duplicate rule.
     */
    public function createRule(array $data): array
    {
        $userId = $this->authContext->userId();

        return $this->ruleRepository->create([
            'module_id' => $data['module_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'match_type' => $data['match_type'] ?? self::MATCH_EXACT,
            'match_fields' => json_encode($data['match_fields'] ?? []),
            'threshold' => $data['threshold'] ?? 0.8,
            'priority' => $data['priority'] ?? 0,
            'created_by' => $userId,
        ]);
    }

    /**
     * Update a duplicate rule.
     */
    public function updateRule(int $id, array $data): ?array
    {
        $rule = $this->ruleRepository->findById($id);

        if (!$rule) {
            throw new \RuntimeException("Rule not found: {$id}");
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }
        if (isset($data['match_type'])) {
            $updateData['match_type'] = $data['match_type'];
        }
        if (isset($data['match_fields'])) {
            $updateData['match_fields'] = json_encode($data['match_fields']);
        }
        if (isset($data['threshold'])) {
            $updateData['threshold'] = $data['threshold'];
        }
        if (isset($data['priority'])) {
            $updateData['priority'] = $data['priority'];
        }

        return $this->ruleRepository->update($id, $updateData);
    }

    /**
     * Delete a duplicate rule.
     */
    public function deleteRule(int $id): bool
    {
        $rule = $this->ruleRepository->findById($id);

        if (!$rule) {
            throw new \RuntimeException("Rule not found: {$id}");
        }

        return $this->ruleRepository->delete($id);
    }

    /**
     * Toggle rule active status.
     */
    public function toggleRuleActive(int $id): ?array
    {
        $rule = $this->ruleRepository->findById($id);

        if (!$rule) {
            throw new \RuntimeException("Rule not found: {$id}");
        }

        return $this->ruleRepository->toggleActive($id);
    }

    // =========================================================================
    // DUPLICATE DETECTION
    // =========================================================================

    /**
     * Check a record for duplicates.
     */
    public function checkForDuplicates(int $moduleId, int $recordId): array
    {
        $record = $this->recordRepository->findByIdAsArray($recordId);

        if (!$record) {
            return [];
        }

        $rules = $this->ruleRepository->getActiveRulesForModule($moduleId);

        $duplicates = [];

        foreach ($rules as $rule) {
            $matches = $this->findMatchesForRule($moduleId, $record, $rule);
            foreach ($matches as $match) {
                $existingKey = array_search($match['record_id'], array_column($duplicates, 'record_id'));
                if ($existingKey === false) {
                    $duplicates[] = $match;
                } else {
                    $duplicates[$existingKey]['score'] = max($duplicates[$existingKey]['score'], $match['score']);
                    $duplicates[$existingKey]['matched_fields'] = array_unique(
                        array_merge($duplicates[$existingKey]['matched_fields'], $match['matched_fields'])
                    );
                }
            }
        }

        usort($duplicates, fn($a, $b) => $b['score'] <=> $a['score']);

        return $duplicates;
    }

    /**
     * Find matches for a specific rule.
     */
    public function findMatchesForRule(int $moduleId, array $record, array $rule): array
    {
        $matches = [];
        $matchFields = $rule['match_fields'] ?? [];
        $matchType = $rule['match_type'] ?? self::MATCH_EXACT;
        $threshold = $rule['threshold'] ?? 0.8;

        if (empty($matchFields)) {
            return [];
        }

        foreach ($matchFields as $fieldConfig) {
            $field = is_array($fieldConfig) ? ($fieldConfig['field'] ?? null) : $fieldConfig;
            $weight = is_array($fieldConfig) ? ($fieldConfig['weight'] ?? 1.0) : 1.0;

            if (!$field) {
                continue;
            }

            $fieldValue = $record['data'][$field] ?? null;
            if (!$fieldValue) {
                continue;
            }

            $matchingRecords = $this->recordRepository->findMatchingRecords(
                $moduleId,
                $record['id'],
                $field,
                $fieldValue,
                $matchType
            );

            foreach ($matchingRecords as $match) {
                $score = $this->calculateMatchScore($record, $match, $matchFields, $matchType);

                if ($score >= $threshold) {
                    $existingKey = array_search($match['id'], array_column($matches, 'record_id'));

                    if ($existingKey === false) {
                        $matches[] = [
                            'record_id' => $match['id'],
                            'record' => $match,
                            'score' => $score,
                            'matched_fields' => [$field],
                            'rule_id' => $rule['id'],
                        ];
                    } else {
                        $matches[$existingKey]['matched_fields'][] = $field;
                        $matches[$existingKey]['score'] = max($matches[$existingKey]['score'], $score);
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * Run duplicate scan for a module.
     */
    public function runDuplicateScan(int $moduleId, ?int $limit = null): array
    {
        $rules = $this->ruleRepository->getActiveRulesForModule($moduleId);

        if (empty($rules)) {
            return ['scanned' => 0, 'duplicates_found' => 0];
        }

        $records = $this->recordRepository->findByModuleId($moduleId, $limit);
        $scanned = 0;
        $duplicatesFound = 0;

        foreach ($records as $record) {
            $duplicates = $this->checkForDuplicates($moduleId, $record['id']);
            $scanned++;

            foreach ($duplicates as $duplicate) {
                if ($duplicate['score'] >= 0.7) {
                    $candidate = $this->createCandidate(
                        $moduleId,
                        $record['id'],
                        $duplicate['record_id'],
                        $duplicate['score'],
                        $duplicate['matched_fields'] ?? []
                    );

                    if (!empty($candidate['created_at']) && strtotime($candidate['created_at']) > time() - 5) {
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
                $merged[$field] = $value;
            } elseif (isset($config[$field])) {
                $source = $config[$field];
                if ($source === 'duplicate') {
                    $merged[$field] = $value;
                } elseif ($source === 'concat') {
                    $merged[$field] = $masterData[$field] . '; ' . $value;
                }
            }
        }

        return $merged;
    }

    /**
     * Transfer related records from duplicate to master.
     */
    private function transferRelatedRecords(int $fromRecordId, int $toRecordId): void
    {
        DB::table('activities')
            ->where('subject_type', 'App\\Models\\ModuleRecord')
            ->where('subject_id', $fromRecordId)
            ->update(['subject_id' => $toRecordId]);

        DB::table('notes')
            ->where('notable_type', 'App\\Models\\ModuleRecord')
            ->where('notable_id', $fromRecordId)
            ->update(['notable_id' => $toRecordId]);

        DB::table('attachments')
            ->where('attachable_type', 'App\\Models\\ModuleRecord')
            ->where('attachable_id', $fromRecordId)
            ->update(['attachable_id' => $toRecordId]);
    }

    /**
     * Calculate match score between two records.
     */
    private function calculateMatchScore(array $recordA, array $recordB, array $matchFields, string $matchType): float
    {
        $totalWeight = 0;
        $matchedWeight = 0;

        foreach ($matchFields as $fieldConfig) {
            $field = is_array($fieldConfig) ? ($fieldConfig['field'] ?? null) : $fieldConfig;
            $weight = is_array($fieldConfig) ? ($fieldConfig['weight'] ?? 1.0) : 1.0;

            if (!$field) {
                continue;
            }

            $totalWeight += $weight;

            $valueA = $recordA['data'][$field] ?? null;
            $valueB = $recordB['data'][$field] ?? null;

            if ($valueA && $valueB) {
                $similarity = $this->calculateFieldSimilarity($valueA, $valueB, $matchType);
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
            self::MATCH_FUZZY => 1 - (levenshtein($stringA, $stringB) / max(strlen($stringA), strlen($stringB), 1)),
            self::MATCH_PHONETIC => soundex($stringA) === soundex($stringB) ? 0.9 : 0,
            default => 0,
        };
    }
}
