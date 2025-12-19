<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pipeline extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'module_id',
        'stage_field_api_name',
        'is_active',
        'settings',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'module_id' => 'integer',
        'is_active' => 'boolean',
        'settings' => 'array',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'settings' => '{}',
    ];

    /**
     * Get the module this pipeline belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the stages for this pipeline.
     */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('display_order');
    }

    /**
     * Get the user who created this pipeline.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this pipeline.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include active pipelines.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by module.
     */
    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Get records in this pipeline grouped by stage.
     */
    public function getRecordsByStage(): array
    {
        $stageFieldName = $this->stage_field_api_name;
        $stages = $this->stages()->get();

        $result = [];

        foreach ($stages as $stage) {
            $records = ModuleRecord::where('module_id', $this->module_id)
                ->whereRaw("data->>? = ?", [$stageFieldName, $stage->id])
                ->get();

            $result[$stage->id] = [
                'stage' => $stage,
                'records' => $records,
                'count' => $records->count(),
            ];
        }

        return $result;
    }
}
