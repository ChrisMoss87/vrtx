<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Domain\Analytics\Repositories\AnalyticsAlertRepositoryInterface;
use App\Domain\Analytics\Repositories\AnalyticsAlertHistoryRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsAlertHistory;
use App\Services\AI\AiService;
use App\Services\Reporting\ReportService;
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
    // Feature flag for AI-enhanced anomaly detection
    protected bool $aiAnomalyDetection = false;

    public function __construct(
        private AnalyticsAlertRepositoryInterface $alertRepository,
        private AnalyticsAlertHistoryRepositoryInterface $historyRepository,
        private ModuleRecordRepositoryInterface $moduleRecordRepository,
        private AiService $aiService,
        private ReportService $reportService,
    ) {}

    /**
     * Process all alerts that are due for checking.
     */
    public function processAlerts(): array
    {
        $alerts = $this->alertRepository->getDueForCheck();
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
    public function checkAlert(object $alert): bool
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
            $this->alertRepository->recordTrigger($alert->id);
        } else {
            $this->alertRepository->recordCheck($alert->id);
        }

        return $triggered;
    }

    /**
     * Calculate the current metric value for an alert.
     */
    protected function calculateMetricValue(object $alert): float
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
    protected function calculateModuleMetric(object $alert): float
    {
        $field = $alert->metric_field ?? 'id';
        $aggregation = $alert->aggregation;
        $filters = $alert->filters ?? [];

        return $this->moduleRecordRepository->calculateMetric(
            $alert->module_id,
            $field,
            $aggregation,
            $filters
        );
    }

    /**
     * Check threshold alert condition.
     */
    protected function checkThreshold(object $alert, float $metricValue): bool
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
            $this->createHistoryEntry($alert->id, $metricValue, [
                'threshold_value' => $threshold,
                'message' => "Metric value ({$metricValue}) {$operator} threshold ({$threshold})",
            ]);
        }

        return $triggered;
    }

    /**
     * Check anomaly detection alert condition.
     */
    protected function checkAnomaly(object $alert, float $metricValue): bool
    {
        $config = $alert->condition_config;
        $sensitivity = $config['sensitivity'] ?? 'medium';
        $baselinePeriods = $config['baseline_periods'] ?? 7;
        $minDeviationPercent = $config['min_deviation_percent'] ?? 20;

        // Calculate baseline from historical data
        $baseline = $this->historyRepository->calculateBaseline($alert->id, $baselinePeriods);

        // Fallback if no historical data
        if ($baseline === 0.0) {
            $baseline = $metricValue * 0.9;
        }

        if ($baseline === 0.0) {
            return false;
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
            $aiInsight = null;
            if ($this->aiAnomalyDetection && $this->aiService->canUse()) {
                $aiInsight = $this->getAiAnomalyInsight($alert, $metricValue, $baseline, $deviation);
            }

            $this->createHistoryEntry($alert->id, $metricValue, [
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
    protected function checkTrend(object $alert, float $metricValue): bool
    {
        $config = $alert->condition_config;
        $direction = $config['direction'] ?? 'decreasing';
        $periods = $config['periods'] ?? 3;
        $minChangePercent = $config['min_change_percent'] ?? 10;

        // Get historical values
        $historicalValues = $this->historyRepository->getHistoricalValues($alert->id, $periods);

        if (count($historicalValues) < $periods) {
            return false;
        }

        // Check if trend is consistent
        $isDecreasing = true;
        $isIncreasing = true;

        for ($i = 1; $i < count($historicalValues); $i++) {
            $change = $historicalValues[$i] - $historicalValues[$i - 1];
            if ($change >= 0) $isDecreasing = false;
            if ($change <= 0) $isIncreasing = false;
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
            $this->createHistoryEntry($alert->id, $metricValue, [
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
    protected function checkComparison(object $alert, float $metricValue): bool
    {
        $config = $alert->condition_config;
        $compareTo = $config['compare_to'] ?? 'previous_period';
        $changeType = $config['change_type'] ?? 'percent';
        $threshold = (float) ($config['threshold'] ?? 15);
        $direction = $config['direction'] ?? 'any';

        $periodDays = match ($compareTo) {
            'previous_day' => 1,
            'previous_week' => 7,
            'previous_month' => 30,
            'previous_quarter' => 90,
            'previous_year' => 365,
            'previous_period' => 7,
            default => 7,
        };

        $comparisonValue = $this->historyRepository->getComparisonValue($alert->id, $periodDays);

        if ($comparisonValue === null || $comparisonValue === 0.0) {
            return false;
        }

        $change = $metricValue - $comparisonValue;
        $percentChange = ($change / $comparisonValue) * 100;

        $changeToCheck = $changeType === 'percent' ? abs($percentChange) : abs($change);

        $directionMatches = match ($direction) {
            'increase' => $change > 0,
            'decrease' => $change < 0,
            'any' => true,
            default => true,
        };

        $triggered = $directionMatches && $changeToCheck >= $threshold;

        if ($triggered) {
            $this->createHistoryEntry($alert->id, $metricValue, [
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
     * Get AI insight for anomaly.
     */
    protected function getAiAnomalyInsight(object $alert, float $value, float $baseline, float $deviation): ?string
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
    protected function createHistoryEntry(int $alertId, float $metricValue, array $data): array
    {
        return $this->historyRepository->create([
            'alert_id' => $alertId,
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
        return $this->historyRepository->getUnacknowledgedForUser($userId);
    }

    /**
     * Acknowledge an alert.
     */
    public function acknowledgeAlert(int $historyId, int $userId, ?string $note = null): void
    {
        $this->historyRepository->acknowledge($historyId, $userId, $note);
    }

    /**
     * Get alert statistics.
     */
    public function getAlertStats(?int $userId = null): array
    {
        return [
            'total_alerts' => $this->alertRepository->getTotalCount($userId),
            'active_alerts' => $this->alertRepository->getActiveCount($userId),
            'triggered_today' => $this->historyRepository->getTriggeredTodayCount($userId),
            'unacknowledged' => $this->historyRepository->getUnacknowledgedCount($userId),
        ];
    }
}
