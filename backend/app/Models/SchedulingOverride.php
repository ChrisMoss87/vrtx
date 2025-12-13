<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchedulingOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'is_available',
        'start_time',
        'end_time',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
        'is_available' => 'boolean',
    ];

    /**
     * Get the user who owns this override.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    /**
     * Scope for date range.
     */
    public function scopeInRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Check if this is a day-off override (completely unavailable).
     */
    public function getIsDayOffAttribute(): bool
    {
        return !$this->is_available && empty($this->start_time) && empty($this->end_time);
    }

    /**
     * Check if this is a custom hours override.
     */
    public function getIsCustomHoursAttribute(): bool
    {
        return $this->is_available && !empty($this->start_time) && !empty($this->end_time);
    }
}
