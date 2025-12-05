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
     */
    protected function getKpiData(): array
    {
        $config = $this->config;

        if (!isset($config['module_id']) || !isset($config['aggregation'])) {
            return ['value' => 0, 'label' => $this->title];
        }

        // This would be handled by the ReportService
        return [
            'value' => 0,
            'label' => $this->title,
            'change' => null,
            'change_type' => null,
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
            ->where('is_completed', false)
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
