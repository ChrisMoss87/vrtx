<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Entities\Report;
use App\Domain\Reporting\ValueObjects\ReportType;

/**
 * Domain service for executing reports.
 *
 * This service contains the core business logic for executing different
 * report types and applying filters, grouping, and aggregations.
 */
class ReportExecutionService
{
    /**
     * Build report configuration for execution.
     *
     * @return array<string, mixed>
     */
    public function buildExecutionConfig(Report $report): array
    {
        return [
            'module_id' => $report->moduleId(),
            'type' => $report->type()->value,
            'chart_type' => $report->chartType()?->value,
            'filters' => $report->filters(),
            'grouping' => $report->grouping(),
            'aggregations' => $report->aggregations(),
            'sorting' => $report->sorting(),
            'date_range' => $report->dateRange()?->toArray(),
            'config' => $report->config(),
        ];
    }

    /**
     * Determine if report execution should use cache.
     */
    public function shouldUseCache(Report $report, bool $forceFresh = false): bool
    {
        if ($forceFresh) {
            return false;
        }

        return $report->isCacheValid();
    }

    /**
     * Calculate cache TTL based on report configuration.
     */
    public function calculateCacheTTL(Report $report): int
    {
        // Check if report has a custom cache TTL in config
        if (isset($report->config()['cache_ttl'])) {
            return (int) $report->config()['cache_ttl'];
        }

        // Default TTL based on report type
        return match ($report->type()) {
            ReportType::TABLE => 5,      // 5 minutes for table reports (data changes frequently)
            ReportType::CHART => 15,     // 15 minutes for charts
            ReportType::SUMMARY => 30,   // 30 minutes for summaries
            ReportType::MATRIX => 60,    // 1 hour for matrix reports
            ReportType::PIVOT => 60,     // 1 hour for pivot tables
        };
    }

    /**
     * Validate report configuration before execution.
     *
     * @return array<string> List of validation errors
     */
    public function validateReportConfig(Report $report): array
    {
        $errors = [];

        // Chart type validation
        if ($report->type() === ReportType::CHART && $report->chartType() === null) {
            $errors[] = 'Chart type is required for chart reports';
        }

        // Aggregations validation
        if ($report->type()->requiresAggregations() && empty($report->aggregations())) {
            $errors[] = 'Aggregations are required for this report type';
        }

        // Grouping validation for certain report types
        if (in_array($report->type(), [ReportType::CHART, ReportType::MATRIX, ReportType::PIVOT])) {
            if (empty($report->grouping())) {
                $errors[] = 'Grouping is required for this report type';
            }
        }

        // Matrix-specific validation
        if ($report->type() === ReportType::MATRIX) {
            $config = $report->config();
            if (empty($config['row_field'])) {
                $errors[] = 'Row field is required for matrix reports';
            }
            if (empty($config['col_field'])) {
                $errors[] = 'Column field is required for matrix reports';
            }
        }

        return $errors;
    }

    /**
     * Determine the limit for table reports.
     */
    public function getTableLimit(Report $report): int
    {
        $config = $report->config();
        $limit = $config['limit'] ?? 1000;

        // Ensure limit is within reasonable bounds
        return min(max($limit, 1), 10000);
    }

    /**
     * Check if report should be executed (scheduling check).
     */
    public function shouldExecuteScheduled(Report $report): bool
    {
        $schedule = $report->schedule();

        if ($schedule === null || empty($schedule)) {
            return false;
        }

        // Check if schedule is enabled
        if (isset($schedule['enabled']) && !$schedule['enabled']) {
            return false;
        }

        // Check last run time
        $lastRunAt = $report->lastRunAt();
        if ($lastRunAt === null) {
            return true;
        }

        // Check frequency
        $frequency = $schedule['frequency'] ?? 'daily';
        $now = new \DateTimeImmutable();
        $lastRun = $lastRunAt->toDateTime();

        return match ($frequency) {
            'hourly' => $now->getTimestamp() - $lastRun->getTimestamp() >= 3600,
            'daily' => $now->format('Y-m-d') !== $lastRun->format('Y-m-d'),
            'weekly' => $now->format('Y-W') !== $lastRun->format('Y-W'),
            'monthly' => $now->format('Y-m') !== $lastRun->format('Y-m'),
            default => false,
        };
    }
}
