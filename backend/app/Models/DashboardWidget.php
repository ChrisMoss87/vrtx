<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id',
        'report_id',
        'title',
        'type',
        'config',
        'position',
        'size',
        'refresh_interval',
    ];

    protected $casts = [
        'dashboard_id' => 'integer',
        'report_id' => 'integer',
        'config' => 'array',
        'position' => 'integer',
        'size' => 'array',
        'refresh_interval' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'config' => '{}',
        'position' => 0,
        'size' => '{"w": 6, "h": 4}',
        'refresh_interval' => 0,
    ];

    // Widget types
    public const TYPE_REPORT = 'report';
    public const TYPE_KPI = 'kpi';
    public const TYPE_CHART = 'chart';
    public const TYPE_TABLE = 'table';
    public const TYPE_ACTIVITY = 'activity';
    public const TYPE_PIPELINE = 'pipeline';
    public const TYPE_TASKS = 'tasks';
    public const TYPE_CALENDAR = 'calendar';
    public const TYPE_TEXT = 'text';
    public const TYPE_IFRAME = 'iframe';

    /**
     * Get the dashboard this widget belongs to.
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    /**
     * Get the report this widget displays (if type is report).
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Get available widget types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_REPORT => 'Report',
            self::TYPE_KPI => 'KPI Card',
            self::TYPE_CHART => 'Quick Chart',
            self::TYPE_TABLE => 'Data Table',
            self::TYPE_ACTIVITY => 'Activity Feed',
            self::TYPE_PIPELINE => 'Pipeline Summary',
            self::TYPE_TASKS => 'My Tasks',
            self::TYPE_CALENDAR => 'Calendar',
            self::TYPE_TEXT => 'Text/Markdown',
            self::TYPE_IFRAME => 'Embed URL',
        ];
    }

    /**
     * Check if this widget requires a report.
     */
    public function requiresReport(): bool
    {
        return in_array($this->type, [self::TYPE_REPORT, self::TYPE_CHART, self::TYPE_TABLE]);
    }

    /**
     * Get the widget data.
     */
    public function getData(): array
    {
        if ($this->report_id && $this->report) {
            // Return report data
            if ($this->report->isCacheValid()) {
                return $this->report->cached_result;
            }
            // Report service will need to be called to refresh
            return [];
        }

        // Handle other widget types
        return match ($this->type) {
            self::TYPE_KPI => $this->getKpiData(),
            self::TYPE_ACTIVITY => $this->getActivityData(),
            self::TYPE_PIPELINE => $this->getPipelineData(),
            self::TYPE_TASKS => $this->getTasksData(),
            default => [],
        };
    }

    /**
     * Get KPI widget data.
     * Works with module_records table which stores data as JSON.
     */
    protected function getKpiData(): array
    {
        $config = $this->config;

        if (!isset($config['module_id']) || !isset($config['aggregation'])) {
            return ['value' => 0, 'label' => $this->title];
        }

        $module = Module::find($config['module_id']);
        if (!$module) {
            return ['value' => 0, 'label' => $this->title];
        }

        // Query module_records table with JSON data extraction
        $query = ModuleRecord::where('module_id', $config['module_id'])
            ->whereNull('deleted_at');

        // Apply filters on JSON data fields
        if (!empty($config['filters'])) {
            foreach ($config['filters'] as $filter) {
                $this->applyJsonFilter($query, $filter);
            }
        }

        // Apply date range if configured
        if (!empty($config['date_range'])) {
            $this->applyDateRange($query, $config['date_range']);
        }

        // Calculate the aggregation
        $aggregation = $config['aggregation'];
        $field = $config['field'] ?? null;
        $value = 0;

        switch ($aggregation) {
            case 'count':
                $value = $query->count();
                break;
            case 'sum':
                if ($field) {
                    // Sum JSON field values
                    $value = (float) $query->selectRaw("SUM(CAST(data->>'$field' AS NUMERIC))")->value('sum') ?? 0;
                }
                break;
            case 'avg':
                if ($field) {
                    $value = round((float) $query->selectRaw("AVG(CAST(data->>'$field' AS NUMERIC))")->value('avg') ?? 0, 2);
                }
                break;
            case 'min':
                if ($field) {
                    $value = (float) $query->selectRaw("MIN(CAST(data->>'$field' AS NUMERIC))")->value('min') ?? 0;
                }
                break;
            case 'max':
                if ($field) {
                    $value = (float) $query->selectRaw("MAX(CAST(data->>'$field' AS NUMERIC))")->value('max') ?? 0;
                }
                break;
            default:
                $value = $query->count();
        }

        // Calculate comparison if configured
        $changePercent = null;
        $changeType = null;

        if (!empty($config['compare_range'])) {
            $compareQuery = ModuleRecord::where('module_id', $config['module_id'])
                ->whereNull('deleted_at');

            // Apply same filters
            if (!empty($config['filters'])) {
                foreach ($config['filters'] as $filter) {
                    $this->applyJsonFilter($compareQuery, $filter);
                }
            }

            // Apply comparison date range
            $this->applyDateRange($compareQuery, [
                'field' => $config['date_range']['field'] ?? 'created_at',
                'range' => $config['compare_range'],
            ]);

            // Calculate comparison value
            $compareField = $config['field'] ?? null;
            $compareValue = match ($aggregation) {
                'count' => $compareQuery->count(),
                'sum' => $compareField ? ((float) $compareQuery->selectRaw("SUM(CAST(data->>'$compareField' AS NUMERIC))")->value('sum') ?? 0) : 0,
                'avg' => $compareField ? round((float) $compareQuery->selectRaw("AVG(CAST(data->>'$compareField' AS NUMERIC))")->value('avg') ?? 0, 2) : 0,
                'min' => $compareField ? ((float) $compareQuery->selectRaw("MIN(CAST(data->>'$compareField' AS NUMERIC))")->value('min') ?? 0) : 0,
                'max' => $compareField ? ((float) $compareQuery->selectRaw("MAX(CAST(data->>'$compareField' AS NUMERIC))")->value('max') ?? 0) : 0,
                default => $compareQuery->count(),
            };

            if ($compareValue > 0) {
                $changePercent = round((($value - $compareValue) / $compareValue) * 100, 1);
                $changeType = $changePercent > 0 ? 'increase' : ($changePercent < 0 ? 'decrease' : 'no_change');
            }
        }

        return [
            'value' => $value,
            'label' => $this->title,
            'change_percent' => $changePercent,
            'change_type' => $changeType,
        ];
    }

    /**
     * Get activity widget data.
     */
    protected function getActivityData(): array
    {
        $limit = $this->config['limit'] ?? 10;

        return Activity::query()
            ->latest()
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get pipeline widget data.
     */
    protected function getPipelineData(): array
    {
        $pipelineId = $this->config['pipeline_id'] ?? null;

        if (!$pipelineId) {
            return [];
        }

        $pipeline = Pipeline::with('stages')->find($pipelineId);

        if (!$pipeline) {
            return [];
        }

        return [
            'pipeline' => $pipeline->toArray(),
            'stages' => $pipeline->stages->map(function ($stage) {
                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'count' => ModuleRecord::where('data->stage_id', $stage->id)->count(),
                    'value' => ModuleRecord::where('data->stage_id', $stage->id)->sum('data->value') ?? 0,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get tasks widget data.
     */
    protected function getTasksData(): array
    {
        $userId = $this->config['user_id'] ?? auth()->id();
        $limit = $this->config['limit'] ?? 10;

        return Activity::query()
            ->where('type', 'task')
            ->where('user_id', $userId)
            ->whereNull('completed_at')
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Apply a filter to a query on JSON data field.
     */
    protected function applyJsonFilter($query, array $filter): void
    {
        if (!isset($filter['field']) || !isset($filter['operator'])) {
            return;
        }

        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'] ?? null;

        // Use PostgreSQL JSON operators for data field
        $jsonPath = "data->>'$field'";

        match ($operator) {
            'equals', 'eq', '=' => $query->whereRaw("$jsonPath = ?", [$value]),
            'not_equals', 'ne', '!=' => $query->whereRaw("$jsonPath != ?", [$value]),
            'gt', '>' => $query->whereRaw("CAST($jsonPath AS NUMERIC) > ?", [$value]),
            'gte', '>=' => $query->whereRaw("CAST($jsonPath AS NUMERIC) >= ?", [$value]),
            'lt', '<' => $query->whereRaw("CAST($jsonPath AS NUMERIC) < ?", [$value]),
            'lte', '<=' => $query->whereRaw("CAST($jsonPath AS NUMERIC) <= ?", [$value]),
            'contains' => $query->whereRaw("$jsonPath ILIKE ?", ["%{$value}%"]),
            'in' => is_array($value)
                ? $query->whereRaw("$jsonPath IN (" . implode(',', array_fill(0, count($value), '?')) . ")", $value)
                : null,
            'not_in' => is_array($value)
                ? $query->whereRaw("$jsonPath NOT IN (" . implode(',', array_fill(0, count($value), '?')) . ")", $value)
                : null,
            'is_null' => $query->whereRaw("$jsonPath IS NULL"),
            'is_not_null' => $query->whereRaw("$jsonPath IS NOT NULL"),
            default => null,
        };
    }

    /**
     * Apply a date range filter to a query.
     */
    protected function applyDateRange($query, array $dateRange): void
    {
        $field = $dateRange['field'] ?? 'created_at';
        $range = $dateRange['range'] ?? null;

        // Check if field is a JSON data field or a table column
        $isJsonField = !in_array($field, ['created_at', 'updated_at', 'deleted_at']);
        $fieldExpr = $isJsonField ? "data->>'$field'" : $field;

        // Handle predefined ranges
        if ($range) {
            $now = now();
            [$start, $end] = match ($range) {
                'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
                'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
                'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
                'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
                'last_quarter' => [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()],
                'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
                'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
                'last_7_days' => [$now->copy()->subDays(7)->startOfDay(), $now->copy()->endOfDay()],
                'last_30_days' => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
                'last_90_days' => [$now->copy()->subDays(90)->startOfDay(), $now->copy()->endOfDay()],
                default => [null, null],
            };

            if ($start && $end) {
                if ($isJsonField) {
                    $query->whereRaw("CAST($fieldExpr AS DATE) >= ?", [$start->toDateString()]);
                    $query->whereRaw("CAST($fieldExpr AS DATE) <= ?", [$end->toDateString()]);
                } else {
                    $query->where($field, '>=', $start);
                    $query->where($field, '<=', $end);
                }
            }
        }

        // Handle explicit start/end dates
        if (!empty($dateRange['start'])) {
            if ($isJsonField) {
                $query->whereRaw("CAST($fieldExpr AS DATE) >= ?", [$dateRange['start']]);
            } else {
                $query->where($field, '>=', $dateRange['start']);
            }
        }

        if (!empty($dateRange['end'])) {
            if ($isJsonField) {
                $query->whereRaw("CAST($fieldExpr AS DATE) <= ?", [$dateRange['end']]);
            } else {
                $query->where($field, '<=', $dateRange['end']);
            }
        }
    }
}
