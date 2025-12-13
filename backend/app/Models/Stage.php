<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pipeline_id',
        'name',
        'color',
        'probability',
        'display_order',
        'is_won_stage',
        'is_lost_stage',
        'rotting_days',
        'settings',
    ];

    protected $casts = [
        'pipeline_id' => 'integer',
        'probability' => 'integer',
        'display_order' => 'integer',
        'is_won_stage' => 'boolean',
        'is_lost_stage' => 'boolean',
        'rotting_days' => 'integer',
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'color' => '#6b7280',
        'probability' => 0,
        'display_order' => 0,
        'is_won_stage' => false,
        'is_lost_stage' => false,
        'settings' => '{}',
    ];

    /**
     * Get the pipeline this stage belongs to.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Get the stage history entries for this stage.
     */
    public function stageHistory(): HasMany
    {
        return $this->hasMany(StageHistory::class);
    }

    /**
     * Scope a query to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    /**
     * Get records in this stage.
     *
     * Note: For bulk operations, prefer fetching all records at once and grouping in PHP
     * to avoid N+1 queries. Use Pipeline::getRecordsByStage() for optimized bulk fetching.
     */
    public function getRecords()
    {
        // Use already loaded relation if available to avoid N+1
        $pipeline = $this->relationLoaded('pipeline') ? $this->pipeline : $this->pipeline()->first();
        $stageFieldName = $pipeline->stage_field_api_name;

        return ModuleRecord::where('module_id', $pipeline->module_id)
            ->whereRaw("data->>? = ?", [$stageFieldName, (string) $this->id])
            ->get();
    }

    /**
     * Get the count of records in this stage.
     *
     * Note: For bulk operations, prefer fetching all records at once and counting in PHP
     * to avoid N+1 queries.
     */
    public function getRecordCount(): int
    {
        // Use already loaded relation if available to avoid N+1
        $pipeline = $this->relationLoaded('pipeline') ? $this->pipeline : $this->pipeline()->first();
        $stageFieldName = $pipeline->stage_field_api_name;

        return ModuleRecord::where('module_id', $pipeline->module_id)
            ->whereRaw("data->>? = ?", [$stageFieldName, (string) $this->id])
            ->count();
    }

    /**
     * Get the total value of records in this stage.
     *
     * Note: For bulk operations, prefer fetching all records at once and summing in PHP
     * to avoid N+1 queries.
     */
    public function getTotalValue(string $valueFieldName): float
    {
        // Use already loaded relation if available to avoid N+1
        $pipeline = $this->relationLoaded('pipeline') ? $this->pipeline : $this->pipeline()->first();
        $stageFieldName = $pipeline->stage_field_api_name;

        return (float) ModuleRecord::where('module_id', $pipeline->module_id)
            ->whereRaw("data->>? = ?", [$stageFieldName, (string) $this->id])
            ->selectRaw("SUM((data->>?)::numeric) as total", [$valueFieldName])
            ->value('total') ?? 0;
    }

    /**
     * Efficient bulk method to get all stage data for a pipeline in one query.
     * Returns array keyed by stage_id with count and totalValue.
     *
     * @param Pipeline $pipeline
     * @param string|null $valueFieldName
     * @return array<int, array{count: int, totalValue: float}>
     */
    public static function getStageMetricsForPipeline(Pipeline $pipeline, ?string $valueFieldName = null): array
    {
        $stageFieldName = $pipeline->stage_field_api_name;

        $query = ModuleRecord::where('module_id', $pipeline->module_id)
            ->selectRaw("(data->>?)::text as stage_id, COUNT(*) as count", [$stageFieldName]);

        if ($valueFieldName) {
            $query->selectRaw("COALESCE(SUM((data->>?)::numeric), 0) as total_value", [$valueFieldName]);
        }

        $results = $query->groupByRaw("data->>?", [$stageFieldName])->get();

        $metrics = [];
        foreach ($results as $row) {
            $metrics[$row->stage_id] = [
                'count' => (int) $row->count,
                'totalValue' => (float) ($row->total_value ?? 0),
            ];
        }

        return $metrics;
    }
}
