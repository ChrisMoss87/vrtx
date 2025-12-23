<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ReportTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'module_id',
        'type',
        'chart_type',
        'is_public',
        'config',
        'filters',
        'grouping',
        'aggregations',
        'sorting',
        'date_range',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'config' => 'array',
        'filters' => 'array',
        'grouping' => 'array',
        'aggregations' => 'array',
        'sorting' => 'array',
        'date_range' => 'array',
    ];

    /**
     * Get the user who created this template.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the module this template is for.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Scope to get templates accessible by a user (owned or public).
     */
    public function scopeAccessibleBy(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_public', true);
        });
    }

    /**
     * Scope to filter by module.
     */
    public function scopeForModule(Builder $query, int $moduleId): Builder
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Scope to get public templates.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Convert this template to a report data array.
     */
    public function toReportData(): array
    {
        return [
            'module_id' => $this->module_id,
            'type' => $this->type,
            'chart_type' => $this->chart_type,
            'config' => $this->config ?? [],
            'filters' => $this->filters ?? [],
            'grouping' => $this->grouping ?? [],
            'aggregations' => $this->aggregations ?? [],
            'sorting' => $this->sorting ?? [],
            'date_range' => $this->date_range,
        ];
    }
}
