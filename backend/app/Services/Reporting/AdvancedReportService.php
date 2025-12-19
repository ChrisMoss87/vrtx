<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Domain\Reporting\ValueObjects\CalculatedField;
use App\Domain\Reporting\ValueObjects\ReportJoin;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

/**
 * Advanced reporting service for cross-object queries, calculated fields, and cohort analysis.
 */
class AdvancedReportService
{
    public function __construct(
        protected ReportService $reportService,
    ) {}

    /**
     * Execute a cross-object report with joins.
     *
     * @param array $config Report configuration including:
     *   - module_id: Primary module ID
     *   - joins: Array of ReportJoin configurations
     *   - filters: Filters (can reference joined modules)
     *   - grouping: Grouping fields
     *   - aggregations: Aggregation functions
     *   - calculated_fields: Custom formula fields
     *   - sorting: Sort order
     *   - date_range: Date filtering
     */
    public function executeCrossObjectReport(array $config): array
    {
        $primaryModuleId = $config['module_id'] ?? null;
        $joins = $this->parseJoins($config['joins'] ?? []);
        $filters = $config['filters'] ?? [];
        $grouping = $config['grouping'] ?? [];
        $aggregations = $config['aggregations'] ?? [];
        $calculatedFields = $this->parseCalculatedFields($config['calculated_fields'] ?? []);
        $sorting = $config['sorting'] ?? [];
        $dateRange = $config['date_range'] ?? [];
        $limit = $config['config']['limit'] ?? 1000;
        $type = $config['type'] ?? 'table';

        if (!$primaryModuleId) {
            return ['type' => 'error', 'message' => 'Primary module_id is required'];
        }

        // Build the base query
        $query = $this->buildCrossObjectQuery(
            $primaryModuleId,
            $joins,
            $filters,
            $dateRange
        );

        // Execute based on report type
        return match ($type) {
            'table' => $this->executeTableQuery($query, $joins, $calculatedFields, $sorting, $limit),
            'chart', 'summary' => $this->executeAggregateQuery($query, $joins, $grouping, $aggregations, $calculatedFields, $sorting),
            'matrix' => $this->executeMatrixQuery($query, $joins, $config),
            'cohort' => $this->executeCohortQuery($query, $joins, $config),
            default => $this->executeTableQuery($query, $joins, $calculatedFields, $sorting, $limit),
        };
    }

    /**
     * Build the base cross-object query with joins.
     */
    protected function buildCrossObjectQuery(
        int $primaryModuleId,
        array $joins,
        array $filters,
        array $dateRange
    ): \Illuminate\Database\Query\Builder {
        // Start with primary module
        $query = DB::table('module_records as primary')
            ->where('primary.module_id', $primaryModuleId);

        // Add joins
        foreach ($joins as $join) {
            $this->addJoin($query, $join);
        }

        // Apply filters (can reference joined modules via alias)
        $this->applyFiltersWithJoins($query, $filters, $joins);

        // Apply date range
        $this->applyDateRangeWithJoins($query, $dateRange);

        return $query;
    }

    /**
     * Add a join to the query.
     */
    protected function addJoin(\Illuminate\Database\Query\Builder $query, ReportJoin $join): void
    {
        $joinMethod = match ($join->joinType) {
            ReportJoin::TYPE_INNER => 'join',
            ReportJoin::TYPE_LEFT => 'leftJoin',
            ReportJoin::TYPE_RIGHT => 'rightJoin',
        };

        // Join on the lookup field value matching the target module's record ID
        // Lookup fields store the ID of the related record
        $query->$joinMethod(
            "module_records as {$join->alias}",
            function ($joinQuery) use ($join) {
                $joinQuery->on(
                    DB::raw("(primary.data->>'{$join->sourceField}')::integer"),
                    '=',
                    "{$join->alias}.id"
                );
                $joinQuery->where("{$join->alias}.module_id", '=', $join->targetModuleId);
            }
        );
    }

    /**
     * Apply filters that can reference joined modules.
     */
    protected function applyFiltersWithJoins(
        \Illuminate\Database\Query\Builder $query,
        array $filters,
        array $joins
    ): void {
        $aliases = ['primary'];
        foreach ($joins as $join) {
            $aliases[] = $join->alias;
        }

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? null;

            if (!$field) continue;

            // Parse field reference (e.g., "primary.status" or "company.industry")
            [$alias, $fieldName] = $this->parseFieldReference($field, $aliases);

            // Build column reference
            $column = $this->buildColumnReference($alias, $fieldName);

            $this->applyFilterCondition($query, $column, $operator, $value);
        }
    }

    /**
     * Apply date range filter with join support.
     */
    protected function applyDateRangeWithJoins(
        \Illuminate\Database\Query\Builder $query,
        array $dateRange
    ): void {
        if (empty($dateRange)) return;

        $field = $dateRange['field'] ?? 'created_at';
        $type = $dateRange['type'] ?? null;
        $start = $dateRange['start'] ?? null;
        $end = $dateRange['end'] ?? null;

        // Parse field reference
        [$alias, $fieldName] = $this->parseFieldReference($field, ['primary']);
        $column = $this->buildColumnReference($alias, $fieldName, true);

        if ($type) {
            [$start, $end] = $this->getDateRangeFromType($type);
        }

        if ($start) {
            $query->where($column, '>=', $start);
        }

        if ($end) {
            $query->where($column, '<=', $end);
        }
    }

    /**
     * Execute a table query with joins and calculated fields.
     */
    protected function executeTableQuery(
        \Illuminate\Database\Query\Builder $query,
        array $joins,
        array $calculatedFields,
        array $sorting,
        int $limit
    ): array {
        // Build select list
        $selects = ['primary.id', 'primary.data as primary_data', 'primary.created_at', 'primary.updated_at'];

        foreach ($joins as $join) {
            $selects[] = "{$join->alias}.id as {$join->alias}_id";
            $selects[] = "{$join->alias}.data as {$join->alias}_data";
        }

        // Add calculated fields
        foreach ($calculatedFields as $calc) {
            $sql = $calc->toSqlExpression(fn($f) => $this->fieldToSqlForSelect($f, $joins));
            $selects[] = DB::raw("({$sql}) as \"{$calc->name}\"");
        }

        $query->select($selects);

        // Apply sorting
        foreach ($sorting as $sort) {
            $field = $sort['field'] ?? null;
            $direction = $sort['direction'] ?? 'asc';

            if ($field) {
                [$alias, $fieldName] = $this->parseFieldReference($field, array_merge(['primary'], array_map(fn($j) => $j->alias, $joins)));
                $column = $this->buildColumnReference($alias, $fieldName);
                $query->orderByRaw("{$column} {$direction}");
            }
        }

        // Execute with limit
        $results = $query->limit($limit)->get();

        // Transform results
        $data = $results->map(function ($row) use ($joins, $calculatedFields) {
            $record = [
                'id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];

            // Merge primary data
            $primaryData = json_decode($row->primary_data ?? '{}', true);
            $record = array_merge($record, $primaryData);

            // Add joined data with alias prefix
            foreach ($joins as $join) {
                $joinIdKey = "{$join->alias}_id";
                $joinDataKey = "{$join->alias}_data";

                if (isset($row->$joinIdKey)) {
                    $joinData = json_decode($row->$joinDataKey ?? '{}', true);
                    foreach ($joinData as $key => $value) {
                        $record["{$join->alias}.{$key}"] = $value;
                    }
                    $record["{$join->alias}.id"] = $row->$joinIdKey;
                }
            }

            // Add calculated fields
            foreach ($calculatedFields as $calc) {
                $record[$calc->name] = $row->{$calc->name} ?? null;
            }

            return $record;
        })->toArray();

        return [
            'type' => 'table',
            'data' => $data,
            'total' => count($data),
        ];
    }

    /**
     * Execute an aggregate query with joins and calculated fields.
     */
    protected function executeAggregateQuery(
        \Illuminate\Database\Query\Builder $query,
        array $joins,
        array $grouping,
        array $aggregations,
        array $calculatedFields,
        array $sorting
    ): array {
        if (empty($grouping) && empty($aggregations)) {
            return [
                'type' => 'summary',
                'data' => [['count' => $query->count()]],
                'total' => 1,
            ];
        }

        $selectParts = [];
        $groupByParts = [];
        $aliases = array_merge(['primary'], array_map(fn($j) => $j->alias, $joins));

        // Add grouping fields
        foreach ($grouping as $group) {
            $field = $group['field'] ?? $group;
            $groupAlias = $group['alias'] ?? str_replace('.', '_', $field);
            $interval = $group['interval'] ?? null;

            [$tableAlias, $fieldName] = $this->parseFieldReference($field, $aliases);

            if ($interval && in_array($fieldName, ['created_at', 'updated_at'])) {
                // Date grouping
                $column = "{$tableAlias}.{$fieldName}";
                $selectParts[] = "DATE_TRUNC('{$interval}', {$column}) as \"{$groupAlias}\"";
                $groupByParts[] = "DATE_TRUNC('{$interval}', {$column})";
            } else {
                $column = $this->buildColumnReference($tableAlias, $fieldName);
                $selectParts[] = "{$column} as \"{$groupAlias}\"";
                $groupByParts[] = $column;
            }
        }

        // Add aggregations
        foreach ($aggregations as $agg) {
            $function = strtoupper($agg['function'] ?? 'count');
            $field = $agg['field'] ?? '*';
            $aggAlias = $agg['alias'] ?? "{$function}_{$field}";

            if ($field === '*') {
                $selectParts[] = "COUNT(*) as \"{$aggAlias}\"";
            } else {
                [$tableAlias, $fieldName] = $this->parseFieldReference($field, $aliases);
                $column = $this->buildColumnReference($tableAlias, $fieldName, true);

                $selectParts[] = match ($function) {
                    'COUNT' => "COUNT({$column}) as \"{$aggAlias}\"",
                    'COUNT_DISTINCT' => "COUNT(DISTINCT {$column}) as \"{$aggAlias}\"",
                    'SUM' => "SUM(({$column})::numeric) as \"{$aggAlias}\"",
                    'AVG' => "AVG(({$column})::numeric) as \"{$aggAlias}\"",
                    'MIN' => "MIN(({$column})::numeric) as \"{$aggAlias}\"",
                    'MAX' => "MAX(({$column})::numeric) as \"{$aggAlias}\"",
                    default => "COUNT(*) as \"{$aggAlias}\"",
                };
            }
        }

        // Add calculated field aggregations
        foreach ($calculatedFields as $calc) {
            $sql = $calc->toSqlExpression(fn($f) => $this->fieldToSqlForSelect($f, $joins));
            $selectParts[] = "({$sql}) as \"{$calc->name}\"";
        }

        if (empty($aggregations) && empty($calculatedFields)) {
            $selectParts[] = 'COUNT(*) as count';
        }

        // Build and execute query
        $query->selectRaw(implode(', ', $selectParts));

        if (!empty($groupByParts)) {
            $query->groupByRaw(implode(', ', $groupByParts));
        }

        $results = $query->get();

        // Apply sorting to results
        $data = $results->toArray();
        if (!empty($sorting)) {
            $sortField = $sorting[0]['field'] ?? null;
            $sortDir = $sorting[0]['direction'] ?? 'asc';

            if ($sortField) {
                usort($data, function ($a, $b) use ($sortField, $sortDir) {
                    $aVal = (array) $a;
                    $bVal = (array) $b;
                    $aVal = $aVal[$sortField] ?? 0;
                    $bVal = $bVal[$sortField] ?? 0;

                    return $sortDir === 'desc' ? $bVal <=> $aVal : $aVal <=> $bVal;
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
     * Execute a cohort analysis query.
     *
     * Groups records by creation cohort (e.g., month) and tracks a metric over time.
     */
    protected function executeCohortQuery(
        \Illuminate\Database\Query\Builder $query,
        array $joins,
        array $config
    ): array {
        $cohortField = $config['config']['cohort_field'] ?? 'created_at';
        $cohortInterval = $config['config']['cohort_interval'] ?? 'month';
        $metricField = $config['config']['metric_field'] ?? null;
        $metricAggregation = $config['config']['metric_aggregation'] ?? 'count';
        $periodField = $config['config']['period_field'] ?? 'created_at';
        $periodInterval = $config['config']['period_interval'] ?? 'month';

        $aliases = array_merge(['primary'], array_map(fn($j) => $j->alias, $joins));

        // Build cohort column
        [$cohortAlias, $cohortFieldName] = $this->parseFieldReference($cohortField, $aliases);
        $cohortColumn = "{$cohortAlias}.{$cohortFieldName}";

        // Build period column
        [$periodAlias, $periodFieldName] = $this->parseFieldReference($periodField, $aliases);
        $periodColumn = "{$periodAlias}.{$periodFieldName}";

        // Build metric expression
        $metricExpr = 'COUNT(*)';
        if ($metricField && $metricField !== '*') {
            [$metricAlias, $metricFieldName] = $this->parseFieldReference($metricField, $aliases);
            $metricColumn = $this->buildColumnReference($metricAlias, $metricFieldName, true);

            $metricExpr = match (strtoupper($metricAggregation)) {
                'SUM' => "SUM(({$metricColumn})::numeric)",
                'AVG' => "AVG(({$metricColumn})::numeric)",
                'COUNT' => "COUNT({$metricColumn})",
                'COUNT_DISTINCT' => "COUNT(DISTINCT {$metricColumn})",
                default => 'COUNT(*)',
            };
        }

        // Execute cohort query
        $results = $query
            ->selectRaw("
                DATE_TRUNC('{$cohortInterval}', {$cohortColumn}) as cohort,
                DATE_TRUNC('{$periodInterval}', {$periodColumn}) as period,
                {$metricExpr} as value
            ")
            ->groupByRaw("DATE_TRUNC('{$cohortInterval}', {$cohortColumn}), DATE_TRUNC('{$periodInterval}', {$periodColumn})")
            ->orderByRaw("cohort, period")
            ->get();

        // Transform to cohort matrix format
        $cohorts = [];
        $periods = [];
        $matrix = [];

        foreach ($results as $row) {
            $cohort = $row->cohort;
            $period = $row->period;

            if (!in_array($cohort, $cohorts)) {
                $cohorts[] = $cohort;
            }
            if (!in_array($period, $periods)) {
                $periods[] = $period;
            }

            $matrix[$cohort][$period] = $row->value;
        }

        sort($cohorts);
        sort($periods);

        return [
            'type' => 'cohort',
            'cohorts' => $cohorts,
            'periods' => $periods,
            'data' => $matrix,
            'config' => [
                'cohort_interval' => $cohortInterval,
                'period_interval' => $periodInterval,
                'metric_aggregation' => $metricAggregation,
            ],
        ];
    }

    /**
     * Execute a matrix query with joins.
     */
    protected function executeMatrixQuery(
        \Illuminate\Database\Query\Builder $query,
        array $joins,
        array $config
    ): array {
        $rowField = $config['config']['row_field'] ?? null;
        $colField = $config['config']['col_field'] ?? null;
        $valueAgg = $config['aggregations'][0] ?? ['function' => 'count', 'field' => '*'];

        if (!$rowField || !$colField) {
            return ['type' => 'matrix', 'data' => [], 'rows' => [], 'columns' => []];
        }

        $aliases = array_merge(['primary'], array_map(fn($j) => $j->alias, $joins));

        // Build row and column references
        [$rowAlias, $rowFieldName] = $this->parseFieldReference($rowField, $aliases);
        [$colAlias, $colFieldName] = $this->parseFieldReference($colField, $aliases);

        $rowColumn = $this->buildColumnReference($rowAlias, $rowFieldName);
        $colColumn = $this->buildColumnReference($colAlias, $colFieldName);

        // Build aggregation expression
        $aggExpr = 'COUNT(*)';
        if (isset($valueAgg['field']) && $valueAgg['field'] !== '*') {
            [$aggAlias, $aggFieldName] = $this->parseFieldReference($valueAgg['field'], $aliases);
            $aggColumn = $this->buildColumnReference($aggAlias, $aggFieldName, true);

            $aggExpr = match (strtoupper($valueAgg['function'] ?? 'count')) {
                'SUM' => "SUM(({$aggColumn})::numeric)",
                'AVG' => "AVG(({$aggColumn})::numeric)",
                'COUNT' => "COUNT({$aggColumn})",
                default => 'COUNT(*)',
            };
        }

        // Get distinct columns
        $columns = (clone $query)
            ->selectRaw("DISTINCT {$colColumn} as value")
            ->pluck('value')
            ->filter()
            ->values()
            ->toArray();

        // Get aggregated data
        $results = $query
            ->selectRaw("{$rowColumn} as row_label, {$colColumn} as col_label, {$aggExpr} as value")
            ->groupByRaw("{$rowColumn}, {$colColumn}")
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
     * Parse join configurations into ReportJoin objects.
     *
     * @return ReportJoin[]
     */
    protected function parseJoins(array $joins): array
    {
        return array_map(fn($j) => $j instanceof ReportJoin ? $j : ReportJoin::fromArray($j), $joins);
    }

    /**
     * Parse calculated field configurations.
     *
     * @return CalculatedField[]
     */
    protected function parseCalculatedFields(array $fields): array
    {
        return array_map(fn($f) => $f instanceof CalculatedField ? $f : CalculatedField::fromArray($f), $fields);
    }

    /**
     * Parse a field reference like "company.name" or "status".
     *
     * @return array{0: string, 1: string} [alias, fieldName]
     */
    protected function parseFieldReference(string $field, array $validAliases): array
    {
        if (str_contains($field, '.')) {
            [$alias, $fieldName] = explode('.', $field, 2);
            if (in_array($alias, $validAliases)) {
                return [$alias, $fieldName];
            }
        }

        // Default to primary
        return ['primary', $field];
    }

    /**
     * Build a column reference for SQL.
     */
    protected function buildColumnReference(string $alias, string $field, bool $forNumeric = false): string
    {
        // System fields
        if (in_array($field, ['id', 'created_at', 'updated_at', 'module_id'])) {
            return "{$alias}.{$field}";
        }

        // JSON field
        return "{$alias}.data->>'{$field}'";
    }

    /**
     * Convert field reference to SQL for SELECT.
     */
    protected function fieldToSqlForSelect(string $field, array $joins): string
    {
        $aliases = array_merge(['primary'], array_map(fn($j) => $j->alias, $joins));
        [$alias, $fieldName] = $this->parseFieldReference($field, $aliases);
        return $this->buildColumnReference($alias, $fieldName);
    }

    /**
     * Apply a filter condition to the query.
     */
    protected function applyFilterCondition(
        \Illuminate\Database\Query\Builder $query,
        string $column,
        string $operator,
        mixed $value
    ): void {
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

    /**
     * Get date range from predefined type.
     *
     * @return array{0: \DateTimeInterface|null, 1: \DateTimeInterface|null}
     */
    protected function getDateRangeFromType(string $type): array
    {
        return match ($type) {
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

    /**
     * Get available joins for a module (based on lookup fields).
     */
    public function getAvailableJoins(int $moduleId): array
    {
        $module = Module::with(['blocks.fields' => function ($query) {
            $query->where('type', 'lookup');
        }])->find($moduleId);

        if (!$module) {
            return [];
        }

        $joins = [];

        foreach ($module->blocks as $block) {
            foreach ($block->fields as $field) {
                $lookupSettings = $field->lookup_settings;
                if (!$lookupSettings) continue;

                $relatedModule = Module::find($lookupSettings['related_module_id'] ?? 0);
                if (!$relatedModule) continue;

                $joins[] = [
                    'source_field' => $field->api_name,
                    'source_field_label' => $field->label,
                    'target_module_id' => $relatedModule->id,
                    'target_module_name' => $relatedModule->name,
                    'target_module_api_name' => $relatedModule->api_name,
                    'suggested_alias' => $relatedModule->api_name,
                ];
            }
        }

        return $joins;
    }

    /**
     * Get fields from all modules in a cross-object report.
     */
    public function getCrossObjectFields(int $primaryModuleId, array $joins): array
    {
        $fields = [];

        // Primary module fields
        $primaryFields = $this->reportService->getModuleFields($primaryModuleId);
        foreach ($primaryFields as $field) {
            $field['module'] = 'primary';
            $field['qualified_name'] = "primary.{$field['name']}";
            $fields[] = $field;
        }

        // Joined module fields
        foreach ($joins as $join) {
            $join = $join instanceof ReportJoin ? $join : ReportJoin::fromArray($join);
            $joinedFields = $this->reportService->getModuleFields($join->targetModuleId);

            foreach ($joinedFields as $field) {
                $field['module'] = $join->alias;
                $field['qualified_name'] = "{$join->alias}.{$field['name']}";
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
