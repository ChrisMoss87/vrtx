<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MergeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'surviving_record_id',
        'merged_record_ids',
        'field_selections',
        'merged_data',
        'merged_by',
    ];

    protected $casts = [
        'surviving_record_id' => 'integer',
        'merged_record_ids' => 'array',
        'field_selections' => 'array',
        'merged_data' => 'array',
    ];

    /**
     * Get the module this merge belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the surviving record.
     */
    public function survivingRecord(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'surviving_record_id');
    }

    /**
     * Get the user who performed the merge.
     */
    public function mergedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }

    /**
     * Scope to merge logs for a specific module.
     */
    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Get count of merged records.
     */
    public function getMergedCountAttribute(): int
    {
        return count($this->merged_record_ids ?? []);
    }
}
