<?php

namespace App\Services\Lookalike;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LookalikeService
{
    public function getAudiences(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = LookalikeAudience::with(['creator'])
            ->withCount('matches');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'ilike', '%' . $filters['search'] . '%');
        }

        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($perPage);
    }

    public function createAudience(array $data, int $userId): LookalikeAudience
    {
        return DB::table('lookalike_audiences')->insertGetId([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'source_type' => $data['source_type'],
            'source_id' => $data['source_id'] ?? null,
            'source_criteria' => $data['source_criteria'] ?? [],
            'match_criteria' => $data['match_criteria'] ?? $this->getDefaultMatchCriteria(),
            'weights' => $data['weights'] ?? $this->getDefaultWeights(),
            'min_similarity_score' => $data['min_similarity_score'] ?? 70,
            'size_limit' => $data['size_limit'] ?? null,
            'auto_refresh' => $data['auto_refresh'] ?? false,
            'refresh_frequency' => $data['refresh_frequency'] ?? null,
            'export_destinations' => $data['export_destinations'] ?? [],
            'created_by' => $userId,
        ]);
    }

    public function updateAudience(LookalikeAudience $audience, array $data): LookalikeAudience
    {
        $audience->update($data);
        return $audience->fresh();
    }

    public function buildAudience(LookalikeAudience $audience): LookalikeBuildJob
    {
        // Create build job
        $job = $audience->buildJobs()->create([
            'status' => LookalikeBuildJob::STATUS_PENDING,
        ]);

        // Mark audience as building
        $audience->markAsBuilding();

        // In production, this would dispatch a queue job
        // For now, we'll do a simplified synchronous build
        $this->processBuildJob($job);

        return $job->fresh();
    }

    protected function processBuildJob(LookalikeBuildJob $job): void
    {
        $job->start();
        $audience = $job->audience;
        $startTime = now();

        try {
            // Clear existing matches
            $audience->matches()->delete();

            // Get source records
            $sourceRecords = $this->getSourceRecords($audience);
            $audience->update(['source_count' => $sourceRecords->count()]);

            if ($sourceRecords->isEmpty()) {
                $job->fail('No source records found');
                $audience->update(['status' => LookalikeAudience::STATUS_DRAFT]);
                return;
            }

            // Build profile from source records
            $sourceProfile = $this->buildSourceProfile($sourceRecords, $audience->match_criteria);

            // Find potential matches
            $matches = $this->findMatches(
                $sourceProfile,
                $audience->match_criteria,
                $audience->weights,
                $audience->min_similarity_score,
                $audience->size_limit,
                $sourceRecords->pluck('id')->toArray(),
                $job
            );

            // Save matches
            $matchCount = $this->saveMatches($audience, $matches);

            $duration = $startTime->diffInSeconds(now());
            $job->complete();
            $audience->markAsReady($matchCount, $duration);

        } catch (\Exception $e) {
            $job->fail($e->getMessage());
            $audience->update(['status' => LookalikeAudience::STATUS_DRAFT]);
        }
    }

    protected function getSourceRecords(LookalikeAudience $audience): Collection
    {
        // In production, this would query based on source_type and source_id/criteria
        // For now, simulate with contacts module
        $query = DB::table('module_records')->where('module_api_name', 'contacts');

        // Apply source criteria if manual
        if ($audience->source_type === LookalikeAudience::SOURCE_MANUAL) {
            $criteria = $audience->source_criteria;
            if (!empty($criteria['filters'])) {
                foreach ($criteria['filters'] as $filter) {
                    // Apply each filter
                    $query->whereJsonContains('data->' . $filter['field'], $filter['value']);
                }
            }
        }

        return $query->limit(1000)->get();
    }

    protected function buildSourceProfile(Collection $records, array $criteria): array
    {
        $profile = [];

        foreach ($criteria as $criterion => $enabled) {
            if (!$enabled) continue;

            $profile[$criterion] = match ($criterion) {
                LookalikeAudience::CRITERIA_INDUSTRY => $this->aggregateField($records, 'industry'),
                LookalikeAudience::CRITERIA_COMPANY_SIZE => $this->aggregateField($records, 'company_size'),
                LookalikeAudience::CRITERIA_LOCATION => $this->aggregateField($records, 'country'),
                LookalikeAudience::CRITERIA_BEHAVIOR => $this->aggregateBehavior($records),
                LookalikeAudience::CRITERIA_TECHNOLOGY => $this->aggregateField($records, 'technology_stack'),
                LookalikeAudience::CRITERIA_ENGAGEMENT => $this->aggregateEngagement($records),
                LookalikeAudience::CRITERIA_PURCHASE => $this->aggregatePurchase($records),
                default => [],
            };
        }

        return $profile;
    }

    protected function aggregateField(Collection $records, string $field): array
    {
        $values = $records
            ->pluck('data.' . $field)
            ->filter()
            ->countBy()
            ->sortDesc();

        $total = $values->sum();
        if ($total === 0) return [];

        return $values->map(fn($count) => $count / $total * 100)->toArray();
    }

    protected function aggregateBehavior(Collection $records): array
    {
        // Simplified behavior aggregation
        return [
            'avg_page_views' => $records->avg('data.page_views') ?? 0,
            'avg_sessions' => $records->avg('data.sessions') ?? 0,
        ];
    }

    protected function aggregateEngagement(Collection $records): array
    {
        return [
            'avg_email_opens' => $records->avg('data.email_opens') ?? 0,
            'avg_email_clicks' => $records->avg('data.email_clicks') ?? 0,
        ];
    }

    protected function aggregatePurchase(Collection $records): array
    {
        return [
            'avg_purchase_value' => $records->avg('data.total_purchases') ?? 0,
            'purchase_frequency' => $records->avg('data.purchase_count') ?? 0,
        ];
    }

    protected function findMatches(
        array $sourceProfile,
        array $criteria,
        array $weights,
        float $minScore,
        ?int $sizeLimit,
        array $excludeIds,
        LookalikeBuildJob $job
    ): array {
        $matches = [];
        $totalWeight = array_sum($weights);

        // Get potential candidates (not in source)
        $candidates = DB::table('module_records')->where('module_api_name', 'contacts')
            ->whereNotIn('id', $excludeIds)
            ->limit(10000)
            ->get();

        $total = $candidates->count();
        $processed = 0;

        foreach ($candidates as $candidate) {
            $score = 0;
            $factors = [];

            foreach ($criteria as $criterion => $enabled) {
                if (!$enabled || empty($sourceProfile[$criterion])) continue;

                $weight = $weights[$criterion] ?? 1;
                $criterionScore = $this->calculateCriterionScore(
                    $criterion,
                    $sourceProfile[$criterion],
                    $candidate->data ?? []
                );

                $weightedScore = $criterionScore * ($weight / max(1, $totalWeight));
                $score += $weightedScore;
                $factors[$criterion] = round($criterionScore, 2);
            }

            $normalizedScore = min(100, $score * 100);

            if ($normalizedScore >= $minScore) {
                $matches[] = [
                    'contact_id' => $candidate->id,
                    'contact_module' => 'contacts',
                    'similarity_score' => round($normalizedScore, 2),
                    'match_factors' => $factors,
                ];
            }

            $processed++;

            // Update progress every 100 records
            if ($processed % 100 === 0) {
                $progress = (int) (($processed / max(1, $total)) * 100);
                $job->updateProgress($progress, $processed, count($matches));
            }
        }

        // Sort by score and apply limit
        usort($matches, fn($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);

        if ($sizeLimit && count($matches) > $sizeLimit) {
            $matches = array_slice($matches, 0, $sizeLimit);
        }

        return $matches;
    }

    protected function calculateCriterionScore(string $criterion, array $profile, array $candidateData): float
    {
        return match ($criterion) {
            LookalikeAudience::CRITERIA_INDUSTRY,
            LookalikeAudience::CRITERIA_COMPANY_SIZE,
            LookalikeAudience::CRITERIA_LOCATION,
            LookalikeAudience::CRITERIA_TECHNOLOGY => $this->calculateCategoricalScore($criterion, $profile, $candidateData),
            LookalikeAudience::CRITERIA_BEHAVIOR,
            LookalikeAudience::CRITERIA_ENGAGEMENT,
            LookalikeAudience::CRITERIA_PURCHASE => $this->calculateNumericalScore($profile, $candidateData),
            default => 0,
        };
    }

    protected function calculateCategoricalScore(string $field, array $distribution, array $data): float
    {
        $value = $data[$field] ?? null;
        if (!$value || empty($distribution)) return 0;

        // Return the percentage this value represents in source
        return ($distribution[$value] ?? 0) / 100;
    }

    protected function calculateNumericalScore(array $profile, array $data): float
    {
        $scores = [];
        foreach ($profile as $key => $targetValue) {
            if ($targetValue == 0) continue;
            $actualValue = $data[$key] ?? 0;
            // Score based on how close the value is (within 50% is good)
            $ratio = min($actualValue, $targetValue) / max($actualValue, $targetValue, 1);
            $scores[] = $ratio;
        }

        return empty($scores) ? 0 : array_sum($scores) / count($scores);
    }

    protected function saveMatches(LookalikeAudience $audience, array $matches): int
    {
        foreach ($matches as $match) {
            $audience->matches()->create($match);
        }
        return count($matches);
    }

    protected function getDefaultMatchCriteria(): array
    {
        return [
            LookalikeAudience::CRITERIA_INDUSTRY => true,
            LookalikeAudience::CRITERIA_COMPANY_SIZE => true,
            LookalikeAudience::CRITERIA_LOCATION => true,
            LookalikeAudience::CRITERIA_BEHAVIOR => false,
            LookalikeAudience::CRITERIA_TECHNOLOGY => false,
            LookalikeAudience::CRITERIA_ENGAGEMENT => true,
            LookalikeAudience::CRITERIA_PURCHASE => false,
        ];
    }

    protected function getDefaultWeights(): array
    {
        return [
            LookalikeAudience::CRITERIA_INDUSTRY => 25,
            LookalikeAudience::CRITERIA_COMPANY_SIZE => 20,
            LookalikeAudience::CRITERIA_LOCATION => 15,
            LookalikeAudience::CRITERIA_BEHAVIOR => 10,
            LookalikeAudience::CRITERIA_TECHNOLOGY => 10,
            LookalikeAudience::CRITERIA_ENGAGEMENT => 15,
            LookalikeAudience::CRITERIA_PURCHASE => 5,
        ];
    }

    public function getMatches(LookalikeAudience $audience, int $perPage = 50): LengthAwarePaginator
    {
        return $audience->matches()
            ->orderByDesc('similarity_score')
            ->paginate($perPage);
    }

    public function exportAudience(LookalikeAudience $audience, string $destination, int $userId): array
    {
        $log = $audience->exportLogs()->create([
            'destination' => $destination,
            'exported_by' => $userId,
        ]);

        $log->start();

        try {
            $matches = $audience->matches()->get();

            // Format for export
            $exportData = $matches->map(fn($match) => [
                'id' => $match->contact_id,
                'score' => $match->similarity_score,
                'factors' => $match->match_factors,
            ])->toArray();

            // Mark matches as exported
            $audience->matches()->update([
                'exported' => true,
                'exported_at' => now(),
                'export_destination' => $destination,
            ]);

            $log->complete(count($exportData));
            $audience->update(['last_exported_at' => now()]);

            return $exportData;

        } catch (\Exception $e) {
            $log->fail($e->getMessage());
            throw $e;
        }
    }
}
