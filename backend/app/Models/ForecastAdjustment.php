<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastAdjustment extends Model
{
    use HasFactory;

    public const TYPE_CATEGORY_CHANGE = 'category_change';
    public const TYPE_AMOUNT_OVERRIDE = 'amount_override';
    public const TYPE_CLOSE_DATE_CHANGE = 'close_date_change';

    protected $fillable = [
        'user_id',
        'module_record_id',
        'adjustment_type',
        'old_value',
        'new_value',
        'reason',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'module_record_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who made the adjustment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the record this adjustment is for.
     */
    public function moduleRecord(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class);
    }

    /**
     * Scope to get adjustments for a specific record.
     */
    public function scopeForRecord($query, int $recordId)
    {
        return $query->where('module_record_id', $recordId);
    }

    /**
     * Scope to get adjustments of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('adjustment_type', $type);
    }

    /**
     * Get human-readable adjustment description.
     */
    public function getDescriptionAttribute(): string
    {
        return match ($this->adjustment_type) {
            self::TYPE_CATEGORY_CHANGE => "Changed forecast category from '{$this->old_value}' to '{$this->new_value}'",
            self::TYPE_AMOUNT_OVERRIDE => "Override amount changed from {$this->old_value} to {$this->new_value}",
            self::TYPE_CLOSE_DATE_CHANGE => "Expected close date changed from {$this->old_value} to {$this->new_value}",
            default => "Forecast adjustment: {$this->adjustment_type}",
        };
    }
}
