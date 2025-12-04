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
        'settings',
    ];

    protected $casts = [
        'pipeline_id' => 'integer',
        'probability' => 'integer',
        'display_order' => 'integer',
        'is_won_stage' => 'boolean',
        'is_lost_stage' => 'boolean',
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
     */
    public function getRecords()
    {
        $pipeline = $this->pipeline;
        $stageFieldName = $pipeline->stage_field_api_name;

        return ModuleRecord::where('module_id', $pipeline->module_id)
            ->whereRaw("data->>? = ?", [$stageFieldName, (string) $this->id])
            ->get();
    }

    /**
     * Get the count of records in this stage.
     */
    public function getRecordCount(): int
    {
        $pipeline = $this->pipeline;
        $stageFieldName = $pipeline->stage_field_api_name;

        return ModuleRecord::where('module_id', $pipeline->module_id)
            ->whereRaw("data->>? = ?", [$stageFieldName, (string) $this->id])
            ->count();
    }

    /**
     * Get the total value of records in this stage.
     */
    public function getTotalValue(string $valueFieldName): float
    {
        $pipeline = $this->pipeline;
        $stageFieldName = $pipeline->stage_field_api_name;

        return (float) ModuleRecord::where('module_id', $pipeline->module_id)
            ->whereRaw("data->>? = ?", [$stageFieldName, (string) $this->id])
            ->selectRaw("SUM((data->>?)::numeric) as total", [$valueFieldName])
            ->value('total') ?? 0;
    }
}
