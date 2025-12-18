<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsAlertHistory;
use App\Models\Module;
use App\Models\ModuleRecord;
use App\Services\AI\AiService;
use App\Services\Reporting\ReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for processing and triggering analytics alerts.
 *
 * Supports:
 * - Threshold alerts: Trigger when metric crosses a value
 * - Anomaly detection: Trigger when metric deviates from baseline
 * - Trend alerts: Trigger when metric trends in a direction over periods
 * - Comparison alerts: Trigger when period-over-period change exceeds threshold
 */
class AnalyticsAlertService
{
    protected AiService $aiService;
    protected ReportService $reportService;

    // Feature flag for AI-enhanced anomaly detection
    protected bool $aiAnomalyDetection = false;

    public function __construct(AiService $aiService, ReportService $reportService)
    {
        $this->aiService = $aiService;
        $this->reportService = $reportService;
    }

    /**
     * Process all alerts that are due for checking.
     */
    public function processAlerts(): array
    {
        $alerts = AnalyticsAlert::dueForCheck()->get();
        $results = [
            'checked' => 0,
            'triggered' => 0,
            'errors' => 0,
        ];

        foreach ($alerts as $alert) {
            try {
                $triggered = $this->checkAlert($alert);
                $results['checked']++;
                if ($triggered) {
                    $results['triggered']++;
                }
            } catch (\Exception $e) {
                Log::error('Alert check failed', [
                    'alert_id' => $alert->id,
                    'error' => $e->getMessage(),
                ]);
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * Check a single alert and trigger if conditions are met.
     */
    public function checkAlert(AnalyticsAlert $alert): bool
    {
        if ($alert->isInCooldown()) {
            return false;
        }

        $metricValue = $this->calculateMetricValue($alert);

        $triggered = match ($alert->alert_type) {
            AnalyticsAlert::TYPE_THRESHOLD => $this->checkThreshold($alert, $metricValue),
            AnalyticsAlert::TYPE_ANOMALY => $this->checkAnomaly($alert, $metricValue),
            AnalyticsAlert::TYPE_TREND => $this->checkTrend($alert, $metricValue),
            AnalyticsAlert::TYPE_COMPARISON => $this->checkComparison($alert, $metricValue),
            default => false,
        };

        if ($triggered) {
            $alert->recordTrigger();
        } else {
            $alert->recordCheck();
        }

        return $triggered;
    }

    /**
     * Calculate the current metric value for an alert.
     */
    protected function calculateMetricValue(AnalyticsAlert $alert): float
    {
        // If alert is based on a report, execute the report
        if ($alert->report_id) {
            $result = $this->reportService->executeReport($alert->report);

            // Get the primary metric from report result
            if (isset($result['summary']['total'])) {
                return (float) $result['summary']['total'];
            }
            if (isset($result['rows']) && count($result['rows']) > 0) {
                $firstRow = $result['rows'][0];
                // Try to find a numeric value
                foreach ($firstRow as $value) {
                    if (is_numeric($value)) {
                        return (float) $value;
                    }
                }
            }
            return 0;
        }

        // Otherwise, calculate from module directly
        if (!$alert->module_id) {
            throw new \Exception('Alert must have either a report or module configured');
        }

        return $this->calculateModuleMetric($alert);
    }

    /**
     * Calculate metric directly from module data.
     */
    protected function calculateModuleMetric(AnalyticsAlert $alert): float
    {
        $module = $alert->module;
        $query = ModuleRecord::where('module_id', $module->id);

        // Apply filters
        if (!empty($alert->filters)) {
            foreach ($alert->filters as $filter) {
                $query = $this->applyFilter($query, $filter);
            }
        }

        $field = $alert->metric_field ?? 'id';
        $aggregation = $alert->aggregation;

        return match ($aggregation) {
            'count' => (float) $query->count(),
            'sum' => (float) $query->sum("data->{$field}"),
            'avg' => (float) $query->avg("data->{$field}"),
            'min' => (float) $query->min("data->{$field}"),
            'max' => (float) $query->max("data->{$field}"),
            'count_distinct' => (float) $query->distinct("data->{$field}")->count(),
            default => (float) $query->count(),
        };
    }

    /**
     * Apply a filter to the query.
     */
    protected function applyFilter($query, array $filter)
    {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'] ?? null;

        $dbField = "data->{$field}";

        return match ($operator) {
            'equals' => $query->where($dbField, $value),
            'not_equals' => $query->where($dbField, '!=', $value),
            'contains' => $query->where($dbField, 'like', "%{$value}%"),
            'greater_than' => $query->where($dbField, '>', $value),
            'less_than' => $query->where($dbField, '<', $value),
            'greater_or_equal' => $query->where($dbField, '>=', $value),
            'less_or_equal' => $query->where($dbField, '<=', $value),
            'is_empty' => $query->whereNull($dbField),
            'is_not_empty' => $query->whereNotNull($dbField),
            default => $query,
        };
    }

    /**
     * Check threshold alert condition.
     */
    protected function checkThreshold(AnalyticsAlert $alert, float $metricValue): bool
    {
        $config = $alert->condition_config;
        $operator = $config['operator'] ?? 'greater_than';
        $threshold = (float) ($config['value'] ?? 0);

        $triggered = match ($operator) {
            'greater_than' => $metricValue > $threshold,
            'less_than' => $metricValue < $threshold,
            'greater_or_equal' => $metricValue >= $threshold,
            'less_or_equal' => $metricValue <= $threshold,
            'equals' => abs($metricValue - $threshold) < 0.0001,
            'not_equals' => abs($metricValue - $threshold) >= 0.0001,
            default => false,
        };

        if ($triggered) {
            $this->createHistoryEntry($alert, $metricValue, [
                'threshold_value' => $threshold,
                'message' => "Metric value ({$metricValue}) {$operator} threshold ({$threshold})",
            ]);
        }

        return $triggered;
    }

    /**
     * Check anomaly detection alert condition.
     */
    protected function checkAnomaly(AnalyticsAlert $alert, float $metricValue): bool
    {
        $config = $alert->condition_config;
        $sensitivity = $config['sensitivity'] ?? 'medium';
        $baselinePeriods = $config['baseline_periods'] ?? 7;
        $minDeviationPercent = $config['min_deviation_percent'] ?? 20;

        // Calculate baseline from historical data
        $baseline = $this->calculateBaseline($alert, $baselinePeriods);

        if ($baseline === 0.0) {
            return false; // Can't detect anomaly without baseline
        }

        $deviation = abs(($metricValue - $baseline) / $baseline) * 100;

        // Adjust threshold based on sensitivity
        $threshold = match ($sensitivity) {
            'low' => $minDeviationPercent * 1.5,
            'high' => $minDeviationPercent * 0.5,
            default => $minDeviationPercent,
        };

        $triggered = $deviation >= $threshold;

        if ($triggered) {
            // Use AI for additional analysis if enabled
            $aiInsight = null;
            if ($this->aiAnomalyDetection && $this->aiService->canUse()) {
                $aiInsight = $this->getAiAnomalyInsight($alert, $metricValue, $baseline, $deviation);
            }

            $this->createHistoryEntry($alert, $metricValue, [
                'baseline_value' => $baseline,
                'deviation_percent' => $deviation,
                'message' => "Anomaly detected: {$deviation}% deviation from baseline ({$baseline})",
                'context' => [
                    'sensitivity' => $sensitivity,
                    'ai_insight' => $aiInsight,
                ],
            ]);
        }

        return $triggered;
    }

    /**
     * Check trend alert condition.
     */
    protected function checkTrend(AnalyticsAlert $alert, float $metricValue): bool
    {
        $config = $alert->condition_config;
        $direction = $config['direction'] ?? 'decreasing';
        $periods = $config['periods'] ?? 3;
        $minChangePercent = $config['min_change_percent'] ?? 10;

        // Get historical values
        $historicalValues = $this->getHistoricalValues($alert, $periods);

        if (count($historicalValues) < $periods) {
            return false; // Not enough data
        }

        // Check if trend is consistent
        $isDecreasing = true;
        $isIncreasing = true;
        $totalChange = 0;

        for ($i = 1; $i < count($historicalValues); $i++) {
            $change = $historicalValues[$i] - $historicalValues[$i - 1];
            if ($change >= 0) $isDecreasing = false;
            if ($change <= 0) $isIncreasing = false;
            $totalChange += $change;
        }

        // Check current value against last historical
        $lastHistorical = end($historicalValues);
        $currentChange = $metricValue - $lastHistorical;
        if ($currentChange >= 0) $isDecreasing = false;
        if ($currentChange <= 0) $isIncreasing = false;

        // Calculate percent change
        $startValue = reset($historicalValues);
        $percentChange = $startValue != 0 ? abs(($metricValue - $startValue) / $startValue) * 100 : 0;

        $triggered = match ($direction) {
            'decreasing' => $isDecreasing && $percentChange >= $minChangePercent,
            'increasing' => $isIncreasing && $percentChange >= $minChangePercent,
            default => false,
        };

        if ($triggered) {
            $this->createHistoryEntry($alert, $metricValue, [
                'deviation_percent' => $percentChange,
                'message' => "Trend detected: metric {$direction} by {$percentChange}% over {$periods} periods",
                'context' => [
                    'direction' => $direction,
                    'historical_values' => $historicalValues,
                ],
            ]);
        }

        return $triggered;
    }

    /**
     * Check period comparison alert condition.
     */
    protected function checkComparison(AnalyticsAlert $alert, float $metricValue): bool
    {
        $config = $alert->condition_config;
        $compareTo = $config['compare_to'] ?? 'previous_period';
        $changeType = $config['change_type'] ?? 'percent';
        $threshold = (float) ($config['threshold'] ?? 15);
        $direction = $config['direction'] ?? 'any'; // any, increase, decrease

        // Get comparison value
        $comparisonValue = $this->getComparisonValue($alert, $compareTo);

        if ($comparisonValue === null || $comparisonValue === 0.0) {
            return false;
        }

        $change = $metricValue - $comparisonValue;
        $percentChange = ($change / $comparisonValue) * 100;

        $changeToCheck = $changeType === 'percent' ? abs($percentChange) : abs($change);

        // Check direction
        $directionMatches = match ($direction) {
            'increase' => $change > 0,
            'decrease' => $change < 0,
            'any' => true,
            default => true,
        };

        $triggered = $directionMatches && $changeToCheck >= $threshold;

        if ($triggered) {
            $this->createHistoryEntry($alert, $metricValue, [
                'baseline_value' => $comparisonValue,
                'deviation_percent' => $percentChange,
                'message' => "Period comparison: {$percentChange}% change from {$compareTo}",
                'context' => [
                    'compare_to' => $compareTo,
                    'absolute_change' => $change,
                ],
            ]);
        }

        return $triggered;
    }

    /**
     * Calculate baseline from historical alert checks.
     */
    protected function calculateBaseline(AnalyticsAlert $alert, int $periods): float
    {
        // Try to get from history first
        $historicalValues = AnalyticsAlertHistory::where('alert_id', $alert->id)
            ->where('status', AnalyticsAlertHistory::STATUS_RESOLVED)
            ->whereNotNull('metric_value')
            ->orderBy('created_at', 'desc')
            ->limit($periods)
            ->pluck('metric_value')
            ->toArray();

        if (count($historicalValues) >= $periods / 2) {
            return array_sum($historicalValues) / count($historicalValues);
        }

        // Fallback: calculate from current data with date offset
        // This is a simplified approach - in production, you'd want more sophisticated baseline calculation
        return $this->calculateMetricValue($alert) * 0.9; // Assume 10% lower as baseline
    }

    /**
     * Get historical metric values for trend analysis.
     */
    protected function getHistoricalValues(AnalyticsAlert $alert, int $periods): array
    {
        return AnalyticsAlertHistory::where('alert_id', $alert->id)
            ->whereNotNull('metric_value')
            ->orderBy('created_at', 'desc')
            ->limit($periods)
            ->pluck('metric_value')
            ->reverse()
            ->values()
            ->toArray();
    }

    /**
     * Get comparison value for period comparison alerts.
     */
    protected function getComparisonValue(AnalyticsAlert $alert, string $compareTo): ?float
    {
        $periodDays = match ($compareTo) {
            'previous_day' => 1,
            'previous_week' => 7,
            'previous_month' => 30,
            'previous_quarter' => 90,
            'previous_year' => 365,
            'previous_period' => 7, // Default to week
            default => 7,
        };

        // Get the last recorded value from approximately that time
        $history = AnalyticsAlertHistory::where('alert_id', $alert->id)
            ->whereNotNull('metric_value')
            ->whereBetween('created_at', [
                now()->subDays($periodDays + 1),
                now()->subDays($periodDays - 1),
            ])
            ->orderBy('created_at', 'desc')
            ->first();

        return $history?->metric_value;
    }

    /**
     * Get AI insight for anomaly (placeholder for future AI integration).
     */
    protected function getAiAnomalyInsight(AnalyticsAlert $alert, float $value, float $baseline, float $deviation): ?string
    {
        if (!$this->aiService->canUse()) {
            return null;
        }

        try {
            $response = $this->aiService->complete(
                [
                    ['role' => 'system', 'content' => 'You are an analytics expert. Provide a brief (1-2 sentence) insight about the detected anomaly.'],
                    ['role' => 'user', 'content' => "Alert: {$alert->name}\nMetric: {$alert->metric_field}\nCurrent Value: {$value}\nBaseline: {$baseline}\nDeviation: {$deviation}%\n\nProvide a brief insight about possible causes."],
                ],
                'anomaly_insight',
                150,
                0.5
            );

            return $response['content'];
        } catch (\Exception $e) {
            Log::warning('Failed to get AI anomaly insight', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create an alert history entry.
     */
    protected function createHistoryEntry(AnalyticsAlert $alert, float $metricValue, array $data): AnalyticsAlertHistory
    {
        return AnalyticsAlertHistory::create([
            'alert_id' => $alert->id,
            'status' => AnalyticsAlertHistory::STATUS_TRIGGERED,
            'metric_value' => $metricValue,
            'threshold_value' => $data['threshold_value'] ?? null,
            'baseline_value' => $data['baseline_value'] ?? null,
            'deviation_percent' => $data['deviation_percent'] ?? null,
            'message' => $data['message'] ?? null,
            'context' => $data['context'] ?? null,
        ]);
    }

    /**
     * Get unacknowledged alerts for a user.
     */
    public function getUnacknowledgedAlerts(int $userId): array
    {
        return AnalyticsAlertHistory::unacknowledged()
            ->whereHas('alert', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereRaw("notification_config->'recipients' @> ?", [json_encode([$userId])]);
            })
            ->with('alert')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Acknowledge an alert.
     */
    public function acknowledgeAlert(int $historyId, int $userId, ?string $note = null): void
    {
        $history = AnalyticsAlertHistory::findOrFail($historyId);
        $history->acknowledge($userId, $note);
    }

    /**
     * Get alert statistics.
     */
    public function getAlertStats(?int $userId = null): array
    {
        $query = AnalyticsAlert::query();
        if ($userId) {
            $query->where('user_id', $userId);
        }

        return [
            'total_alerts' => $query->count(),
            'active_alerts' => (clone $query)->active()->count(),
            'triggered_today' => AnalyticsAlertHistory::whereDate('created_at', today())
                ->whereHas('alert', $userId ? fn($q) => $q->where('user_id', $userId) : fn($q) => $q)
                ->count(),
            'unacknowledged' => AnalyticsAlertHistory::unacknowledged()
                ->whereHas('alert', $userId ? fn($q) => $q->where('user_id', $userId) : fn($q) => $q)
                ->count(),
        ];
    }
}
