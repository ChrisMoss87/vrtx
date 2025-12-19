<?php

namespace App\Services\Duplicates;

use App\Models\DuplicateCandidate;
use App\Models\DuplicateRule;
use App\Models\Module;
use App\Models\ModuleRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DuplicateDetectionService
{
    /**
     * Check if a record data matches any existing records in the module.
     *
     * @param Module $module
     * @param array $data The data to check
     * @param int|null $excludeRecordId Exclude this record (for updates)
     * @return array Array of potential duplicates with match scores
     */
    public function checkForDuplicates(Module $module, array $data, ?int $excludeRecordId = null): array
    {
        $rules = DuplicateRule::active()
            ->forModule($module->id)
            ->ordered()
            ->get();

        if ($rules->isEmpty()) {
            return [];
        }

        $duplicates = [];

        // Get existing records to compare against
        $query = ModuleRecord::where('module_id', $module->id);
        if ($excludeRecordId) {
            $query->where('id', '!=', $excludeRecordId);
        }

        // Limit to recent/active records for performance
        $existingRecords = $query->limit(10000)->get();

        foreach ($existingRecords as $existingRecord) {
            $matchResult = $this->evaluateRules($rules, $data, $existingRecord->data ?? []);

            if ($matchResult['matched']) {
                $duplicates[] = [
                    'record_id' => $existingRecord->id,
                    'record' => $existingRecord,
                    'match_score' => $matchResult['score'],
                    'matched_rules' => $matchResult['matched_rules'],
                    'action' => $matchResult['action'],
                ];
            }
        }

        // Sort by match score descending
        usort($duplicates, fn ($a, $b) => $b['match_score'] <=> $a['match_score']);

        return $duplicates;
    }

    /**
     * Evaluate all rules against two data sets.
     */
    protected function evaluateRules(Collection $rules, array $dataA, array $dataB): array
    {
        $matchedRules = [];
        $highestScore = 0;
        $action = DuplicateRule::ACTION_WARN;

        foreach ($rules as $rule) {
            $result = $this->evaluateConditions($rule->conditions, $dataA, $dataB);

            if ($result['matched']) {
                $matchedRules[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'score' => $result['score'],
                    'details' => $result['details'],
                ];

                if ($result['score'] > $highestScore) {
                    $highestScore = $result['score'];
                    $action = $rule->action;
                }
            }
        }

        return [
            'matched' => !empty($matchedRules),
            'score' => $highestScore,
            'matched_rules' => $matchedRules,
            'action' => $action,
        ];
    }

    /**
     * Evaluate conditions recursively (supports AND/OR logic).
     */
    protected function evaluateConditions(array $conditions, array $dataA, array $dataB): array
    {
        $logic = $conditions['logic'] ?? 'and';
        $rules = $conditions['rules'] ?? [];

        $results = [];

        foreach ($rules as $rule) {
            // Nested condition group
            if (isset($rule['logic'])) {
                $results[] = $this->evaluateConditions($rule, $dataA, $dataB);
            } else {
                // Single condition
                $results[] = $this->evaluateSingleCondition($rule, $dataA, $dataB);
            }
        }

        return $this->combineResults($results, $logic);
    }

    /**
     * Evaluate a single matching condition.
     */
    protected function evaluateSingleCondition(array $condition, array $dataA, array $dataB): array
    {
        $field = $condition['field'];
        $matchType = $condition['match_type'];
        $threshold = $condition['threshold'] ?? 0.8;

        $valueA = $this->getNestedValue($dataA, $field);
        $valueB = $this->getNestedValue($dataB, $field);

        // Skip if either value is empty
        if (empty($valueA) || empty($valueB)) {
            return [
                'matched' => false,
                'score' => 0,
                'details' => ['field' => $field, 'reason' => 'empty_value'],
            ];
        }

        $score = 0;
        $matched = false;

        switch ($matchType) {
            case DuplicateRule::MATCH_EXACT:
                $score = $this->exactMatch($valueA, $valueB);
                $matched = $score >= 1.0;
                break;

            case DuplicateRule::MATCH_FUZZY:
                $score = $this->fuzzyMatch($valueA, $valueB);
                $matched = $score >= $threshold;
                break;

            case DuplicateRule::MATCH_PHONETIC:
                $score = $this->phoneticMatch($valueA, $valueB);
                $matched = $score >= $threshold;
                break;

            case DuplicateRule::MATCH_EMAIL_DOMAIN:
                $score = $this->emailDomainMatch($valueA, $valueB);
                $matched = $score >= 1.0;
                break;

            default:
                $score = 0;
                $matched = false;
        }

        return [
            'matched' => $matched,
            'score' => $score,
            'details' => [
                'field' => $field,
                'match_type' => $matchType,
                'value_a' => $valueA,
                'value_b' => $valueB,
            ],
        ];
    }

    /**
     * Combine multiple condition results based on logic.
     */
    protected function combineResults(array $results, string $logic): array
    {
        if (empty($results)) {
            return ['matched' => false, 'score' => 0, 'details' => []];
        }

        $details = array_map(fn ($r) => $r['details'] ?? [], $results);

        if ($logic === 'or') {
            // OR: any match is success, use highest score
            $matched = collect($results)->contains('matched', true);
            $score = collect($results)->max('score');
        } else {
            // AND: all must match, use average score
            $matched = collect($results)->every('matched', true);
            $score = $matched ? collect($results)->avg('score') : 0;
        }

        return [
            'matched' => $matched,
            'score' => round($score, 4),
            'details' => $details,
        ];
    }

    /**
     * Exact string match (case-insensitive).
     */
    protected function exactMatch($valueA, $valueB): float
    {
        $a = strtolower(trim((string) $valueA));
        $b = strtolower(trim((string) $valueB));

        return $a === $b ? 1.0 : 0.0;
    }

    /**
     * Fuzzy string match using Levenshtein distance.
     */
    protected function fuzzyMatch($valueA, $valueB): float
    {
        $a = strtolower(trim((string) $valueA));
        $b = strtolower(trim((string) $valueB));

        if ($a === $b) {
            return 1.0;
        }

        $maxLen = max(strlen($a), strlen($b));
        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = levenshtein($a, $b);

        return 1 - ($distance / $maxLen);
    }

    /**
     * Phonetic match using Soundex/Metaphone.
     */
    protected function phoneticMatch($valueA, $valueB): float
    {
        $a = strtolower(trim((string) $valueA));
        $b = strtolower(trim((string) $valueB));

        if ($a === $b) {
            return 1.0;
        }

        // Try Soundex
        $soundexMatch = soundex($a) === soundex($b);

        // Try Metaphone
        $metaphoneA = metaphone($a);
        $metaphoneB = metaphone($b);
        $metaphoneMatch = $metaphoneA === $metaphoneB;

        if ($soundexMatch && $metaphoneMatch) {
            return 1.0;
        } elseif ($soundexMatch || $metaphoneMatch) {
            return 0.8;
        }

        // Partial metaphone match
        if (!empty($metaphoneA) && !empty($metaphoneB)) {
            similar_text($metaphoneA, $metaphoneB, $percent);

            return $percent / 100;
        }

        return 0.0;
    }

    /**
     * Email domain match.
     */
    protected function emailDomainMatch($valueA, $valueB): float
    {
        $domainA = $this->extractEmailDomain($valueA);
        $domainB = $this->extractEmailDomain($valueB);

        if (empty($domainA) || empty($domainB)) {
            return 0.0;
        }

        return strtolower($domainA) === strtolower($domainB) ? 1.0 : 0.0;
    }

    /**
     * Extract domain from email address.
     */
    protected function extractEmailDomain(string $email): ?string
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $email);

            return $parts[1] ?? null;
        }

        return null;
    }

    /**
     * Get nested value from array using dot notation.
     */
    protected function getNestedValue(array $data, string $field)
    {
        $keys = explode('.', $field);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Batch scan for duplicates in a module.
     */
    public function scanModuleForDuplicates(Module $module, ?int $limit = null): int
    {
        $rules = DuplicateRule::active()
            ->forModule($module->id)
            ->ordered()
            ->get();

        if ($rules->isEmpty()) {
            return 0;
        }

        $records = ModuleRecord::where('module_id', $module->id)
            ->orderBy('id')
            ->get();

        $foundCount = 0;
        $processed = [];

        foreach ($records as $index => $recordA) {
            if ($limit && $foundCount >= $limit) {
                break;
            }

            // Compare with remaining records
            for ($i = $index + 1; $i < $records->count(); $i++) {
                $recordB = $records[$i];

                // Skip if already processed this pair
                $pairKey = min($recordA->id, $recordB->id) . '-' . max($recordA->id, $recordB->id);
                if (isset($processed[$pairKey])) {
                    continue;
                }
                $processed[$pairKey] = true;

                // Check if candidate already exists
                $existingCandidate = DuplicateCandidate::where(function ($q) use ($recordA, $recordB) {
                    $q->where('record_id_a', $recordA->id)->where('record_id_b', $recordB->id);
                })->orWhere(function ($q) use ($recordA, $recordB) {
                    $q->where('record_id_a', $recordB->id)->where('record_id_b', $recordA->id);
                })->exists();

                if ($existingCandidate) {
                    continue;
                }

                $result = $this->evaluateRules($rules, $recordA->data ?? [], $recordB->data ?? []);

                if ($result['matched']) {
                    // Always store with smaller ID first for consistency
                    $idA = min($recordA->id, $recordB->id);
                    $idB = max($recordA->id, $recordB->id);

                    DuplicateCandidate::create([
                        'module_id' => $module->id,
                        'record_id_a' => $idA,
                        'record_id_b' => $idB,
                        'match_score' => $result['score'],
                        'matched_rules' => $result['matched_rules'],
                        'status' => DuplicateCandidate::STATUS_PENDING,
                    ]);

                    $foundCount++;

                    if ($limit && $foundCount >= $limit) {
                        break;
                    }
                }
            }
        }

        return $foundCount;
    }

    /**
     * Get duplicate candidates for a module.
     */
    public function getCandidates(int $moduleId, ?string $status = null, int $perPage = 20)
    {
        $query = DuplicateCandidate::with(['recordA', 'recordB', 'reviewer'])
            ->forModule($moduleId)
            ->highestMatch();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Dismiss a duplicate candidate.
     */
    public function dismissCandidate(DuplicateCandidate $candidate, int $userId, ?string $reason = null): void
    {
        $candidate->markAsDismissed($userId, $reason);
    }
}
