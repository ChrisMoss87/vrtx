<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'module_id',
        'user_id',
        'type',
        'chart_type',
        'is_public',
        'is_favorite',
        'config',
        'filters',
        'grouping',
        'aggregations',
        'sorting',
        'date_range',
        'schedule',
        'last_run_at',
        'cached_result',
        'cache_expires_at',
    ];

    protected $casts = [
        'module_id' => 'integer',
        'user_id' => 'integer',
        'is_public' => 'boolean',
        'is_favorite' => 'boolean',
        'config' => 'array',
        'filters' => 'array',
        'grouping' => 'array',
        'aggregations' => 'array',
        'sorting' => 'array',
        'date_range' => 'array',
        'schedule' => 'array',
        'last_run_at' => 'datetime',
        'cached_result' => 'array',
        'cache_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => 'table',
        'chart_type' => null,
        'is_public' => false,
        'is_favorite' => false,
        'config' => '{}',
        'filters' => '[]',
        'grouping' => '[]',
        'aggregations' => '[]',
        'sorting' => '[]',
        'date_range' => '{}',
    ];

    // Report types
    public const TYPE_TABLE = 'table';
    public const TYPE_CHART = 'chart';
    public const TYPE_SUMMARY = 'summary';
    public const TYPE_MATRIX = 'matrix';
    public const TYPE_PIVOT = 'pivot';

    // Chart types
    public const CHART_BAR = 'bar';
    public const CHART_LINE = 'line';
    public const CHART_PIE = 'pie';
    public const CHART_DOUGHNUT = 'doughnut';
    public const CHART_AREA = 'area';
    public const CHART_FUNNEL = 'funnel';
    public const CHART_SCATTER = 'scatter';
    public const CHART_GAUGE = 'gauge';
    public const CHART_KPI = 'kpi';

    // Aggregation functions
    public const AGG_COUNT = 'count';
    public const AGG_SUM = 'sum';
    public const AGG_AVG = 'avg';
    public const AGG_MIN = 'min';
    public const AGG_MAX = 'max';
    public const AGG_COUNT_DISTINCT = 'count_distinct';

    /**
     * Get the module this report belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the dashboard widgets that use this report.
     */
    public function dashboardWidgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class);
    }

    /**
     * Get the shares for this report.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(ReportShare::class);
    }

    /**
     * Get the users this report is shared with directly.
     */
    public function sharedWithUsers()
    {
        return $this->belongsToMany(User::class, 'report_shares')
            ->withPivot(['permission', 'shared_by', 'created_at'])
            ->wherePivotNull('team_id');
    }

    /**
     * Get the teams this report is shared with.
     */
    public function sharedWithTeams()
    {
        return $this->belongsToMany(Team::class, 'report_shares')
            ->withPivot(['permission', 'shared_by', 'created_at'])
            ->wherePivotNull('user_id');
    }

    /**
     * Check if a user has access to this report via sharing.
     */
    public function isSharedWith(int $userId): bool
    {
        return $this->shares()
            ->forUser($userId)
            ->exists();
    }

    /**
     * Check if a user can edit this report via sharing.
     */
    public function canUserEdit(int $userId): bool
    {
        return $this->shares()
            ->forUser($userId)
            ->where('permission', 'edit')
            ->exists();
    }

    /**
     * Scope to public reports.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to favorite reports.
     */
    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Scope to reports accessible by a user (owner, public, or shared).
     */
    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_public', true)
              ->orWhereHas('shares', function ($shareQuery) use ($userId) {
                  $shareQuery->forUser($userId);
              });
        });
    }

    /**
     * Scope to reports by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to reports for a specific module.
     */
    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Check if the report cache is still valid.
     */
    public function isCacheValid(): bool
    {
        if (!$this->cached_result || !$this->cache_expires_at) {
            return false;
        }

        return $this->cache_expires_at->isFuture();
    }

    /**
     * Clear the cached result.
     */
    public function clearCache(): void
    {
        $this->update([
            'cached_result' => null,
            'cache_expires_at' => null,
        ]);
    }

    /**
     * Update the cached result.
     */
    public function updateCache(array $result, int $ttlMinutes = 15): void
    {
        $this->update([
            'cached_result' => $result,
            'cache_expires_at' => now()->addMinutes($ttlMinutes),
            'last_run_at' => now(),
        ]);
    }

    /**
     * Get available report types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_TABLE => 'Table',
            self::TYPE_CHART => 'Chart',
            self::TYPE_SUMMARY => 'Summary',
            self::TYPE_MATRIX => 'Matrix',
            self::TYPE_PIVOT => 'Pivot Table',
        ];
    }

    /**
     * Get available chart types.
     */
    public static function getChartTypes(): array
    {
        return [
            self::CHART_BAR => 'Bar Chart',
            self::CHART_LINE => 'Line Chart',
            self::CHART_PIE => 'Pie Chart',
            self::CHART_DOUGHNUT => 'Doughnut Chart',
            self::CHART_AREA => 'Area Chart',
            self::CHART_FUNNEL => 'Funnel Chart',
            self::CHART_SCATTER => 'Scatter Plot',
            self::CHART_GAUGE => 'Gauge',
            self::CHART_KPI => 'KPI Card',
        ];
    }

    /**
     * Get available aggregation functions.
     */
    public static function getAggregations(): array
    {
        return [
            self::AGG_COUNT => 'Count',
            self::AGG_SUM => 'Sum',
            self::AGG_AVG => 'Average',
            self::AGG_MIN => 'Minimum',
            self::AGG_MAX => 'Maximum',
            self::AGG_COUNT_DISTINCT => 'Count Distinct',
        ];
    }
}
