<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Module;
use App\Models\ModuleRecord;
use App\Models\Report;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Execute a report and return results.
     */
    public function executeReport(Report $report, bool $useCache = true): array
    {
        // Check cache
        if ($useCache && $report->isCacheValid()) {
            return $report->cached_result;
        }

        // Build and execute query
        $result = $this->buildAndExecute($report);

        // Cache result (15 minute default)
        $report->updateCache($result);

        return $result;
    }

    /**
     * Execute a report query without caching.
     */
    public function executeReportQuery(array $config): array
    {
        return $this->buildAndExecuteFromConfig($config);
    }

    /**
     * Build and execute the report query.
     */
    protected function buildAndExecute(Report $report): array
    {
        $config = [
            'module_id' => $report->module_id,
            'type' => $report->type,
            'chart_type' => $report->chart_type,
            'filters' => $report->filters ?? [],
            'grouping' => $report->grouping ?? [],
            'aggregations' => $report->aggregations ?? [],
            'sorting' => $report->sorting ?? [],
            'date_range' => $report->date_range ?? [],
            'config' => $report->config ?? [],
        ];

        return $this->buildAndExecuteFromConfig($config);
    }

    /**
     * Build and execute from raw config.
     */
    protected function buildAndExecuteFromConfig(array $config): array
    {
        $moduleId = $config['module_id'] ?? null;
        $type = $config['type'] ?? 'table';
        $filters = $config['filters'] ?? [];
        $grouping = $config['grouping'] ?? [];
        $aggregations = $config['aggregations'] ?? [];
        $sorting = $config['sorting'] ?? [];
        $dateRange = $config['date_range'] ?? [];
        $limit = $config['config']['limit'] ?? 1000;

        // Start query
        $query = ModuleRecord::query();

        if ($moduleId) {
            $query->where('module_id', $moduleId);
        }

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply date range
        $this->applyDateRange($query, $dateRange);

        // Handle different report types
        return match ($type) {
            'table' => $this->executeTableReport($query, $sorting, $limit),
            'chart', 'summary' => $this->executeAggregateReport($query, $grouping, $aggregations, $sorting),
            'matrix' => $this->executeMatrixReport($query, $config),
            'pivot' => $this->executePivotReport($query, $config),
            default => $this->executeTableReport($query, $sorting, $limit),
        };
    }

    /**
     * Execute a table report (simple list).
     * Uses cursor-based iteration and lazy collection for memory efficiency.
     */
    protected function executeTableReport($query, array $sorting, int $limit): array
    {
        // Apply sorting
        foreach ($sorting as $sort) {
            $field = $sort['field'] ?? null;
            $direction = $sort['direction'] ?? 'asc';

            if ($field) {
                if (in_array($field, ['id', 'created_at', 'updated_at'])) {
                    $query->orderBy($field, $direction);
                } else {
                    $query->orderBy("data->{$field}", $direction);
                }
            }
        }

        // Use cursor for memory efficiency with large datasets
        // Only collect the limited amount we need
        $data = [];
        $count = 0;

        foreach ($query->cursor() as $record) {
            if ($count >= $limit) {
                break;
            }
            $data[] = array_merge(['id' => $record->id], $record->data);
            $count++;
        }

        return [
            'type' => 'table',
            'data' => $data,
            'total' => $count,
        ];
    }

    /**
     * Execute an aggregate report (grouped data for charts/summaries).
     */
    protected function executeAggregateReport($query, array $grouping, array $aggregations, array $sorting): array
    {
        if (empty($grouping) && empty($aggregations)) {
            // Simple count
            return [
                'type' => 'summary',
                'data' => [['count' => $query->count()]],
                'total' => 1,
            ];
        }

        // Build aggregation query
        $selectParts = [];
        $groupByParts = [];

        // Add grouping fields
        foreach ($grouping as $group) {
            $field = $group['field'] ?? $group;
            $alias = $group['alias'] ?? $field;

            if (in_array($field, ['created_at', 'updated_at'])) {
                // Date grouping
                $interval = $group['interval'] ?? 'day';
                $selectParts[] = $this->getDateGroupExpression($field, $interval, $alias);
                $groupByParts[] = $alias;
            } else {
                $selectParts[] = "data->>'{$field}' as \"{$alias}\"";
                $groupByParts[] = "data->>'{$field}'";
            }
        }

        // Add aggregations
        foreach ($aggregations as $agg) {
            $function = $agg['function'] ?? 'count';
            $field = $agg['field'] ?? '*';
            $alias = $agg['alias'] ?? "{$function}_{$field}";

            $selectParts[] = $this->getAggregationExpression($function, $field, $alias);
        }

        // If no aggregations specified, add count
        if (empty($aggregations)) {
            $selectParts[] = 'COUNT(*) as count';
        }

        // Build raw query
        $selectSql = implode(', ', $selectParts);
        $groupBySql = !empty($groupByParts) ? 'GROUP BY ' . implode(', ', $groupByParts) : '';

        // Execute
        $results = DB::table('module_records')
            ->selectRaw($selectSql)
            ->whereRaw($query->toBase()->wheres ? $this->buildWhereClause($query) : '1=1')
            ->groupByRaw(!empty($groupByParts) ? implode(', ', $groupByParts) : '1')
            ->get();

        // Apply sorting to results
        $data = $results->toArray();
        if (!empty($sorting)) {
            $sortField = $sorting[0]['field'] ?? null;
            $sortDir = $sorting[0]['direction'] ?? 'asc';

            if ($sortField) {
                usort($data, function ($a, $b) use ($sortField, $sortDir) {
                    $aVal = $a->$sortField ?? 0;
                    $bVal = $b->$sortField ?? 0;

                    if ($sortDir === 'desc') {
                        return $bVal <=> $aVal;
                    }
                    return $aVal <=> $bVal;
                });
            }
        }

        return [
            'type' => 'aggregate',
            'data' => array_map(fn($row) => (array) $row, $data),
            'total' => count($data),
            'grouping' => $grouping,
            'aggregations' => $aggregations,
        ];
    }

    /**
     * Execute a matrix report (two-dimensional grouping).
     */
    protected function executeMatrixReport($query, array $config): array
    {
        $rowField = $config['config']['row_field'] ?? null;
        $colField = $config['config']['col_field'] ?? null;
        $valueAgg = $config['aggregations'][0] ?? ['function' => 'count', 'field' => '*'];

        if (!$rowField || !$colField) {
            return ['type' => 'matrix', 'data' => [], 'rows' => [], 'columns' => []];
        }

        // Get distinct values for columns
        $columns = DB::table('module_records')
            ->selectRaw("DISTINCT data->>'{$colField}' as value")
            ->whereRaw($query->toBase()->wheres ? $this->buildWhereClause($query) : '1=1')
            ->pluck('value')
            ->filter()
            ->values()
            ->toArray();

        // Get aggregated data
        $aggExpr = $this->getAggregationExpression($valueAgg['function'], $valueAgg['field'], 'value');

        $results = DB::table('module_records')
            ->selectRaw("data->>'{$rowField}' as row_label, data->>'{$colField}' as col_label, {$aggExpr}")
            ->whereRaw($query->toBase()->wheres ? $this->buildWhereClause($query) : '1=1')
            ->groupByRaw("data->>'{$rowField}', data->>'{$colField}'")
            ->get();

        // Transform to matrix format
        $matrix = [];
        $rows = [];

        foreach ($results as $row) {
            if (!isset($matrix[$row->row_label])) {
                $matrix[$row->row_label] = array_fill_keys($columns, 0);
                $rows[] = $row->row_label;
            }
            $matrix[$row->row_label][$row->col_label] = $row->value;
        }

        return [
            'type' => 'matrix',
            'data' => $matrix,
            'rows' => $rows,
            'columns' => $columns,
        ];
    }

    /**
     * Execute a pivot report.
     */
    protected function executePivotReport($query, array $config): array
    {
        // Similar to matrix but with multiple aggregations
        return $this->executeMatrixReport($query, $config);
    }

    /**
     * Apply filters to query.
     */
    protected function applyFilters($query, array $filters): void
    {
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? null;

            if (!$field) continue;

            // System fields vs data fields
            $isSystemField = in_array($field, ['id', 'module_id', 'created_at', 'updated_at']);
            $column = $isSystemField ? $field : "data->>'{$field}'";

            match ($operator) {
                'equals' => $query->whereRaw("{$column} = ?", [$value]),
                'not_equals' => $query->whereRaw("{$column} != ?", [$value]),
                'contains' => $query->whereRaw("{$column} ILIKE ?", ["%{$value}%"]),
                'not_contains' => $query->whereRaw("{$column} NOT ILIKE ?", ["%{$value}%"]),
                'starts_with' => $query->whereRaw("{$column} ILIKE ?", ["{$value}%"]),
                'ends_with' => $query->whereRaw("{$column} ILIKE ?", ["%{$value}"]),
                'greater_than' => $query->whereRaw("({$column})::numeric > ?", [$value]),
                'less_than' => $query->whereRaw("({$column})::numeric < ?", [$value]),
                'greater_than_or_equal' => $query->whereRaw("({$column})::numeric >= ?", [$value]),
                'less_than_or_equal' => $query->whereRaw("({$column})::numeric <= ?", [$value]),
                'between' => $query->whereRaw("({$column})::numeric BETWEEN ? AND ?", [$value[0] ?? 0, $value[1] ?? 0]),
                'in' => $query->whereIn(DB::raw($column), (array) $value),
                'not_in' => $query->whereNotIn(DB::raw($column), (array) $value),
                'is_empty' => $query->whereRaw("({$column} IS NULL OR {$column} = '')"),
                'is_not_empty' => $query->whereRaw("({$column} IS NOT NULL AND {$column} != '')"),
                default => null,
            };
        }
    }

    /**
     * Apply date range filter.
     */
    protected function applyDateRange($query, array $dateRange): void
    {
        $field = $dateRange['field'] ?? 'created_at';
        $type = $dateRange['type'] ?? null;
        $start = $dateRange['start'] ?? null;
        $end = $dateRange['end'] ?? null;

        if (!$type && !$start && !$end) return;

        $isSystemField = in_array($field, ['created_at', 'updated_at']);
        $column = $isSystemField ? $field : "data->>'{$field}'";

        if ($type) {
            // Predefined ranges
            [$start, $end] = match ($type) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
                'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
                'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
                'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
                'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
                'this_quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
                'last_quarter' => [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
                'this_year' => [now()->startOfYear(), now()->endOfYear()],
                'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
                'last_7_days' => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
                'last_30_days' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
                'last_90_days' => [now()->subDays(90)->startOfDay(), now()->endOfDay()],
                default => [null, null],
            };
        }

        if ($start) {
            $query->where($column, '>=', $start);
        }

        if ($end) {
            $query->where($column, '<=', $end);
        }
    }

    /**
     * Get SQL expression for date grouping.
     */
    protected function getDateGroupExpression(string $field, string $interval, string $alias): string
    {
        return match ($interval) {
            'hour' => "DATE_TRUNC('hour', {$field}) as \"{$alias}\"",
            'day' => "DATE_TRUNC('day', {$field}) as \"{$alias}\"",
            'week' => "DATE_TRUNC('week', {$field}) as \"{$alias}\"",
            'month' => "DATE_TRUNC('month', {$field}) as \"{$alias}\"",
            'quarter' => "DATE_TRUNC('quarter', {$field}) as \"{$alias}\"",
            'year' => "DATE_TRUNC('year', {$field}) as \"{$alias}\"",
            default => "DATE_TRUNC('day', {$field}) as \"{$alias}\"",
        };
    }

    /**
     * Get SQL expression for aggregation.
     */
    protected function getAggregationExpression(string $function, string $field, string $alias): string
    {
        $column = $field === '*' ? '*' : "(data->>'{$field}')::numeric";

        return match ($function) {
            'count' => "COUNT({$column}) as \"{$alias}\"",
            'count_distinct' => "COUNT(DISTINCT {$column}) as \"{$alias}\"",
            'sum' => "SUM({$column}) as \"{$alias}\"",
            'avg' => "AVG({$column}) as \"{$alias}\"",
            'min' => "MIN({$column}) as \"{$alias}\"",
            'max' => "MAX({$column}) as \"{$alias}\"",
            default => "COUNT(*) as \"{$alias}\"",
        };
    }

    /**
     * Build WHERE clause from query builder.
     */
    protected function buildWhereClause($query): string
    {
        // This is a simplified version - in production you'd want to properly
        // extract and rebuild the where clause from the query builder
        $wheres = $query->toBase()->wheres ?? [];

        if (empty($wheres)) {
            return '1=1';
        }

        $conditions = [];
        foreach ($wheres as $where) {
            if ($where['type'] === 'Basic') {
                $conditions[] = "{$where['column']} {$where['operator']} '{$where['value']}'";
            }
        }

        return implode(' AND ', $conditions) ?: '1=1';
    }

    /**
     * Get available fields for a module (for report builder).
     */
    public function getModuleFields(int $moduleId): array
    {
        // Eager load fields with their options to avoid N+1 queries
        $module = Module::with(['fields.options', 'blocks.fields.options'])->find($moduleId);

        if (!$module) {
            return [];
        }

        $fields = [];

        // System fields
        $fields[] = ['name' => 'id', 'label' => 'ID', 'type' => 'number', 'system' => true];
        $fields[] = ['name' => 'created_at', 'label' => 'Created At', 'type' => 'datetime', 'system' => true];
        $fields[] = ['name' => 'updated_at', 'label' => 'Updated At', 'type' => 'datetime', 'system' => true];

        // Module fields
        foreach ($module->blocks as $block) {
            foreach ($block->fields as $field) {
                $fields[] = [
                    'name' => $field->api_name,
                    'label' => $field->label,
                    'type' => $field->type,
                    'system' => false,
                    'options' => $field->options?->map(fn($o) => ['value' => $o->value, 'label' => $o->label])->toArray(),
                ];
            }
        }

        return $fields;
    }

    /**
     * Calculate KPI value.
     */
    public function calculateKpi(array $config): array
    {
        $moduleId = $config['module_id'] ?? null;
        $aggregation = $config['aggregation'] ?? 'count';
        $field = $config['field'] ?? '*';
        $filters = $config['filters'] ?? [];
        $dateRange = $config['date_range'] ?? [];
        $compareRange = $config['compare_range'] ?? null;

        // Current value
        $query = ModuleRecord::query();
        if ($moduleId) {
            $query->where('module_id', $moduleId);
        }
        $this->applyFilters($query, $filters);
        $this->applyDateRange($query, $dateRange);

        $currentValue = $this->getAggregateValue($query, $aggregation, $field);

        // Comparison value (if requested)
        $previousValue = null;
        $change = null;
        $changePercent = null;

        if ($compareRange) {
            $compareQuery = ModuleRecord::query();
            if ($moduleId) {
                $compareQuery->where('module_id', $moduleId);
            }
            $this->applyFilters($compareQuery, $filters);
            $this->applyDateRange($compareQuery, $compareRange);

            $previousValue = $this->getAggregateValue($compareQuery, $aggregation, $field);

            if ($previousValue > 0) {
                $change = $currentValue - $previousValue;
                $changePercent = round(($change / $previousValue) * 100, 2);
            }
        }

        return [
            'value' => $currentValue,
            'previous_value' => $previousValue,
            'change' => $change,
            'change_percent' => $changePercent,
            'change_type' => $change > 0 ? 'increase' : ($change < 0 ? 'decrease' : 'neutral'),
        ];
    }

    /**
     * Get aggregate value from query.
     */
    protected function getAggregateValue($query, string $aggregation, string $field): float
    {
        $column = $field === '*' ? '*' : "data->{$field}";

        return match ($aggregation) {
            'count' => (float) $query->count(),
            'sum' => (float) ($query->sum(DB::raw("(data->>'{$field}')::numeric")) ?? 0),
            'avg' => (float) ($query->avg(DB::raw("(data->>'{$field}')::numeric")) ?? 0),
            'min' => (float) ($query->min(DB::raw("(data->>'{$field}')::numeric")) ?? 0),
            'max' => (float) ($query->max(DB::raw("(data->>'{$field}')::numeric")) ?? 0),
            default => (float) $query->count(),
        };
    }

    /**
     * Export report to various formats.
     * For large exports, consider using streamExportToCsv() instead.
     */
    public function exportReport(Report $report, string $format = 'csv'): string
    {
        $result = $this->executeReport($report, useCache: false);
        $data = $result['data'] ?? [];

        return match ($format) {
            'csv' => $this->toCsv($data),
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            default => $this->toCsv($data),
        };
    }

    /**
     * Stream a large report export directly to a file path.
     * Much more memory efficient for large datasets.
     *
     * @param Report $report The report to export
     * @param string $filePath The path to write the export to
     * @param string $format Export format (csv, json)
     * @return int Number of records exported
     */
    public function streamExportToCsv(Report $report, string $filePath): int
    {
        $config = [
            'module_id' => $report->module_id,
            'type' => $report->type,
            'filters' => $report->filters ?? [],
            'sorting' => $report->sorting ?? [],
            'date_range' => $report->date_range ?? [],
        ];

        $query = ModuleRecord::query();

        if ($config['module_id']) {
            $query->where('module_id', $config['module_id']);
        }

        $this->applyFilters($query, $config['filters']);
        $this->applyDateRange($query, $config['date_range']);

        // Apply sorting
        foreach ($config['sorting'] as $sort) {
            $field = $sort['field'] ?? null;
            $direction = $sort['direction'] ?? 'asc';

            if ($field) {
                if (in_array($field, ['id', 'created_at', 'updated_at'])) {
                    $query->orderBy($field, $direction);
                } else {
                    $query->orderBy("data->{$field}", $direction);
                }
            }
        }

        $output = fopen($filePath, 'w');
        if ($output === false) {
            throw new \RuntimeException("Could not open file for writing: {$filePath}");
        }

        $headerWritten = false;
        $count = 0;

        // Use cursor to stream records one at a time
        foreach ($query->cursor() as $record) {
            $row = array_merge(['id' => $record->id], $record->data);

            // Write header on first row
            if (!$headerWritten) {
                fputcsv($output, array_keys($row));
                $headerWritten = true;
            }

            fputcsv($output, array_values($row));
            $count++;

            // Periodically clear memory
            if ($count % 1000 === 0) {
                gc_collect_cycles();
            }
        }

        fclose($output);

        return $count;
    }

    /**
     * Stream JSON export for large datasets.
     * Writes records as a JSON array incrementally.
     *
     * @param Report $report The report to export
     * @param string $filePath The path to write the export to
     * @return int Number of records exported
     */
    public function streamExportToJson(Report $report, string $filePath): int
    {
        $config = [
            'module_id' => $report->module_id,
            'filters' => $report->filters ?? [],
            'sorting' => $report->sorting ?? [],
            'date_range' => $report->date_range ?? [],
        ];

        $query = ModuleRecord::query();

        if ($config['module_id']) {
            $query->where('module_id', $config['module_id']);
        }

        $this->applyFilters($query, $config['filters']);
        $this->applyDateRange($query, $config['date_range']);

        $output = fopen($filePath, 'w');
        if ($output === false) {
            throw new \RuntimeException("Could not open file for writing: {$filePath}");
        }

        fwrite($output, "[\n");

        $count = 0;
        $first = true;

        foreach ($query->cursor() as $record) {
            $row = array_merge(['id' => $record->id], $record->data);

            if (!$first) {
                fwrite($output, ",\n");
            }
            $first = false;

            fwrite($output, json_encode($row, JSON_PRETTY_PRINT));
            $count++;

            // Periodically clear memory
            if ($count % 1000 === 0) {
                gc_collect_cycles();
            }
        }

        fwrite($output, "\n]");
        fclose($output);

        return $count;
    }

    /**
     * Convert data to CSV.
     */
    protected function toCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Header row
        fputcsv($output, array_keys($data[0]));

        // Data rows
        foreach ($data as $row) {
            fputcsv($output, array_values($row));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
