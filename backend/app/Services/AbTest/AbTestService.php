<?php

namespace App\Services\AbTest;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AbTestService
{
    public function getTests(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = AbTest::with(['variants', 'winnerVariant', 'creator']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'ilike', '%' . $filters['search'] . '%');
        }

        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($perPage);
    }

    public function createTest(array $data, int $userId): AbTest
    {
        $test = DB::table('ab_tests')->insertGetId([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'goal' => $data['goal'] ?? AbTest::GOAL_CONVERSION,
            'min_sample_size' => $data['min_sample_size'] ?? 100,
            'confidence_level' => $data['confidence_level'] ?? 95.00,
            'auto_select_winner' => $data['auto_select_winner'] ?? true,
            'scheduled_end_at' => $data['scheduled_end_at'] ?? null,
            'created_by' => $userId,
        ]);

        // Create default control variant (A)
        $test->variants()->create([
            'name' => 'Control (Original)',
            'variant_code' => 'A',
            'content' => $data['control_content'] ?? [],
            'traffic_percentage' => 50,
            'is_control' => true,
        ]);

        return $test;
    }

    public function createVariant(AbTest $test, array $data): AbTestVariant
    {
        $nextCode = $this->getNextVariantCode($test);

        $variant = $test->variants()->create([
            'name' => $data['name'] ?? "Variant {$nextCode}",
            'variant_code' => $nextCode,
            'content' => $data['content'] ?? [],
            'traffic_percentage' => $data['traffic_percentage'] ?? $this->calculateDefaultTrafficPercentage($test),
            'is_control' => false,
        ]);

        // Rebalance traffic if needed
        $this->rebalanceTraffic($test);

        return $variant;
    }

    public function updateVariant(AbTestVariant $variant, array $data): AbTestVariant
    {
        $variant->update($data);
        return $variant->fresh();
    }

    public function deleteVariant(AbTestVariant $variant): void
    {
        $test = $variant->test;
        $variant->delete();
        $this->rebalanceTraffic($test);
    }

    public function startTest(AbTest $test): AbTest
    {
        if ($test->variants()->count() < 2) {
            throw new \InvalidArgumentException('A/B test must have at least 2 variants');
        }

        $test->start();
        return $test->fresh();
    }

    public function selectVariantForVisitor(AbTest $test, ?string $visitorId = null): ?AbTestVariant
    {
        if (!$test->isRunning()) {
            // Return winner or control if test is not running
            if ($test->winner_variant_id) {
                return $test->winnerVariant;
            }
            return $test->variants()->where('is_control', true)->first();
        }

        $activeVariants = $test->activeVariants()->get();
        if ($activeVariants->isEmpty()) {
            return null;
        }

        // Use consistent hashing if visitor ID provided
        if ($visitorId) {
            $hash = crc32($visitorId . $test->id);
            $percentage = $hash % 100;
        } else {
            $percentage = rand(0, 99);
        }

        // Select variant based on traffic percentage
        $cumulative = 0;
        foreach ($activeVariants as $variant) {
            $cumulative += $variant->traffic_percentage;
            if ($percentage < $cumulative) {
                return $variant;
            }
        }

        return $activeVariants->last();
    }

    public function getTestStatistics(AbTest $test): array
    {
        $variants = $test->variants()->with('results')->get();
        $statistics = [];

        foreach ($variants as $variant) {
            $impressions = $variant->getImpressions();
            $conversions = $variant->getConversions();
            $clicks = $variant->getClicks();
            $opens = $variant->getOpens();

            $statistics[] = [
                'id' => $variant->id,
                'name' => $variant->name,
                'variant_code' => $variant->variant_code,
                'is_control' => $variant->is_control,
                'is_winner' => $variant->is_winner,
                'traffic_percentage' => $variant->traffic_percentage,
                'impressions' => $impressions,
                'conversions' => $conversions,
                'clicks' => $clicks,
                'opens' => $opens,
                'conversion_rate' => $impressions > 0 ? ($conversions / $impressions) * 100 : 0,
                'click_rate' => $impressions > 0 ? ($clicks / $impressions) * 100 : 0,
                'open_rate' => $impressions > 0 ? ($opens / $impressions) * 100 : 0,
            ];
        }

        // Calculate statistical significance if we have enough data
        $significance = $this->calculateStatisticalSignificance($statistics, $test->goal);

        return [
            'variants' => $statistics,
            'significance' => $significance,
            'has_winner' => $test->winner_variant_id !== null,
            'recommended_winner' => $significance['recommended_winner'] ?? null,
        ];
    }

    public function checkAndDeclareWinner(AbTest $test): ?AbTestVariant
    {
        if (!$test->isRunning() || !$test->auto_select_winner) {
            return null;
        }

        $statistics = $this->getTestStatistics($test);

        // Check if we have enough samples
        $totalImpressions = array_sum(array_column($statistics['variants'], 'impressions'));
        if ($totalImpressions < $test->min_sample_size * count($statistics['variants'])) {
            return null;
        }

        // Check if we have statistical significance
        if ($statistics['significance']['is_significant'] ?? false) {
            $winnerId = $statistics['recommended_winner'];
            if ($winnerId) {
                $winner = DB::table('ab_test_variants')->where('id', $winnerId)->first();
                if ($winner) {
                    $winner->declareWinner();
                    return $winner;
                }
            }
        }

        return null;
    }

    protected function getNextVariantCode(AbTest $test): string
    {
        $existingCodes = $test->variants()->pluck('variant_code')->toArray();
        $codes = range('A', 'Z');

        foreach ($codes as $code) {
            if (!in_array($code, $existingCodes)) {
                return $code;
            }
        }

        throw new \RuntimeException('Maximum number of variants reached');
    }

    protected function calculateDefaultTrafficPercentage(AbTest $test): int
    {
        $variantCount = $test->variants()->count() + 1;
        return (int) floor(100 / $variantCount);
    }

    protected function rebalanceTraffic(AbTest $test): void
    {
        $variants = $test->activeVariants()->get();
        $count = $variants->count();

        if ($count === 0) {
            return;
        }

        $percentage = (int) floor(100 / $count);
        $remainder = 100 - ($percentage * $count);

        foreach ($variants as $index => $variant) {
            $extra = $index < $remainder ? 1 : 0;
            $variant->update(['traffic_percentage' => $percentage + $extra]);
        }
    }

    protected function calculateStatisticalSignificance(array $variants, string $goal): array
    {
        if (count($variants) < 2) {
            return ['is_significant' => false];
        }

        // Find control variant
        $control = collect($variants)->firstWhere('is_control', true);
        if (!$control) {
            $control = $variants[0];
        }

        $rateKey = match ($goal) {
            AbTest::GOAL_CLICK_RATE => 'click_rate',
            AbTest::GOAL_OPEN_RATE => 'open_rate',
            default => 'conversion_rate',
        };

        $controlRate = $control[$rateKey] ?? 0;
        $controlSample = $control['impressions'] ?? 0;

        $bestVariant = null;
        $bestImprovement = 0;
        $isSignificant = false;

        foreach ($variants as $variant) {
            if ($variant['is_control']) {
                continue;
            }

            $variantRate = $variant[$rateKey] ?? 0;
            $variantSample = $variant['impressions'] ?? 0;

            // Simple Z-test for proportions
            if ($controlSample > 30 && $variantSample > 30 && ($controlRate + $variantRate) > 0) {
                $pooledRate = ($controlRate * $controlSample + $variantRate * $variantSample) / ($controlSample + $variantSample);

                if ($pooledRate > 0 && $pooledRate < 100) {
                    $se = sqrt($pooledRate * (100 - $pooledRate) * (1/$controlSample + 1/$variantSample));

                    if ($se > 0) {
                        $z = ($variantRate - $controlRate) / $se;

                        // 95% confidence = Z > 1.96
                        if (abs($z) > 1.96) {
                            $improvement = $variantRate - $controlRate;
                            if ($improvement > $bestImprovement) {
                                $bestImprovement = $improvement;
                                $bestVariant = $variant['id'];
                                $isSignificant = true;
                            }
                        }
                    }
                }
            }
        }

        return [
            'is_significant' => $isSignificant,
            'recommended_winner' => $bestVariant,
            'improvement' => $bestImprovement,
        ];
    }
}
