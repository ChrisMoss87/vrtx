<?php

declare(strict_types=1);

namespace App\Domain\Plugin\Services;

use App\Domain\Plugin\ValueObjects\PluginSlug;

final class PluginUsageService
{
    /**
     * Calculate usage metrics.
     *
     * @return array{used: int, limit: ?int, remaining: ?int, percentage: ?float, overage: int, overage_cost: float}
     */
    public function calculateUsageMetrics(
        int $used,
        ?int $limit,
        ?float $overageRate = null,
    ): array {
        $remaining = null;
        $percentage = null;
        $overage = 0;
        $overageCost = 0.0;

        if ($limit !== null) {
            $remaining = max(0, $limit - $used);
            $overage = max(0, $used - $limit);

            if ($limit > 0) {
                $percentage = min(100, round(($used / $limit) * 100, 1));
            }
        }

        if ($overageRate !== null && $overage > 0) {
            $overageCost = $overage * $overageRate;
        }

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'overage' => $overage,
            'overage_cost' => $overageCost,
        ];
    }

    /**
     * Check if usage limit is reached.
     */
    public function isLimitReached(int $used, ?int $limit): bool
    {
        if ($limit === null) {
            return false;
        }

        return $used >= $limit;
    }

    /**
     * Check if usage is approaching limit.
     */
    public function isApproachingLimit(int $used, ?int $limit, int $thresholdPercent = 80): bool
    {
        if ($limit === null || $limit === 0) {
            return false;
        }

        $percentage = ($used / $limit) * 100;
        return $percentage >= $thresholdPercent && $percentage < 100;
    }

    /**
     * Get the current billing period.
     *
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}
     */
    public function getCurrentPeriod(): array
    {
        $now = new \DateTimeImmutable();

        return [
            'start' => $now->modify('first day of this month')->setTime(0, 0, 0),
            'end' => $now->modify('last day of this month')->setTime(23, 59, 59),
        ];
    }

    /**
     * Get usage limits for a plan.
     *
     * @return array<string, ?int>
     */
    public function getLimitsForPlan(string $plan): array
    {
        $limits = [
            'free' => [
                'records' => 500,
                'storage_mb' => 1024,
                'api_calls' => 1000,
                'workflows' => 5,
                'blueprints' => 3,
            ],
            'starter' => [
                'records' => 10000,
                'storage_mb' => 5120,
                'api_calls' => 2500,
                'workflows' => 10,
                'blueprints' => 5,
            ],
            'professional' => [
                'records' => 100000,
                'storage_mb' => 25600,
                'api_calls' => 5000,
                'workflows' => 25,
                'blueprints' => 15,
            ],
            'business' => [
                'records' => null,
                'storage_mb' => 102400,
                'api_calls' => 25000,
                'workflows' => null,
                'blueprints' => null,
            ],
            'enterprise' => [
                'records' => null,
                'storage_mb' => null,
                'api_calls' => null,
                'workflows' => null,
                'blueprints' => null,
            ],
        ];

        return $limits[$plan] ?? $limits['free'];
    }

    /**
     * Get the limit for a specific metric in a plan.
     */
    public function getMetricLimit(string $plan, string $metric): ?int
    {
        $limits = $this->getLimitsForPlan($plan);
        return $limits[$metric] ?? null;
    }
}
