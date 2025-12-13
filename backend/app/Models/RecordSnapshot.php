<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordSnapshot extends Model
{
    public const TYPE_FIELD_CHANGE = 'field_change';
    public const TYPE_STAGE_CHANGE = 'stage_change';
    public const TYPE_DAILY = 'daily';
    public const TYPE_MANUAL = 'manual';

    protected $fillable = [
        'module_id',
        'record_id',
        'snapshot_data',
        'snapshot_type',
        'change_summary',
        'created_by',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
        'change_summary' => 'array',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        return $query;
    }

    /**
     * Scope to filter by snapshot type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('snapshot_type', $type);
    }

    /**
     * Get the snapshot closest to (but not after) a given timestamp.
     */
    public static function getAtTimestamp(int $moduleId, int $recordId, string $timestamp): ?self
    {
        return static::forRecord($moduleId, $recordId)
            ->where('created_at', '<=', $timestamp)
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
