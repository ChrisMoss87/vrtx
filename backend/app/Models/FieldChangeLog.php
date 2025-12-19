<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldChangeLog extends Model
{
    protected $fillable = [
        'module_id',
        'record_id',
        'field_api_name',
        'old_value',
        'new_value',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'changed_at' => 'datetime',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope to filter by module and record.
     */
    public function scopeForRecord($query, int $moduleId, int $recordId)
    {
        return $query->where('module_id', $moduleId)
            ->where('record_id', $recordId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeInDateRange($query, ?string $startDate, ?string $endDate)
    {
        if ($startDate) {
            $query->where('changed_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('changed_at', '<=', $endDate);
        }
        return $query;
    }

    /**
     * Scope to filter by field.
     */
    public function scopeForField($query, string $fieldApiName)
    {
        return $query->where('field_api_name', $fieldApiName);
    }

    /**
     * Get changes for a specific record up to a timestamp.
     */
    public static function getChangesUpTo(int $moduleId, int $recordId, string $timestamp)
    {
        return static::forRecord($moduleId, $recordId)
            ->where('changed_at', '<=', $timestamp)
            ->orderBy('changed_at', 'asc')
            ->get();
    }
}
