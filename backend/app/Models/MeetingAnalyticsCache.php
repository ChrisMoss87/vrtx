<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingAnalyticsCache extends Model
{
    use HasFactory;
    protected $table = 'meeting_analytics_cache';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'period',
        'period_start',
        'total_meetings',
        'total_duration_minutes',
        'unique_stakeholders',
        'meetings_per_week',
        'calculated_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'calculated_at' => 'datetime',
        'meetings_per_week' => 'decimal:2',
    ];

    public const ENTITY_DEAL = 'deal';
    public const ENTITY_ACCOUNT = 'account';
    public const ENTITY_USER = 'user';

    public const PERIOD_WEEK = 'week';
    public const PERIOD_MONTH = 'month';
    public const PERIOD_QUARTER = 'quarter';

    public function scopeForEntity($query, string $type, int $id)
    {
        return $query->where('entity_type', $type)->where('entity_id', $id);
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    public static function getOrCalculate(string $entityType, int $entityId, string $period): ?self
    {
        $periodStart = match ($period) {
            self::PERIOD_WEEK => now()->startOfWeek()->toDateString(),
            self::PERIOD_MONTH => now()->startOfMonth()->toDateString(),
            self::PERIOD_QUARTER => now()->firstOfQuarter()->toDateString(),
            default => now()->startOfMonth()->toDateString(),
        };

        return self::firstOrNew([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'period' => $period,
            'period_start' => $periodStart,
        ]);
    }

    public function getTotalHours(): float
    {
        return round($this->total_duration_minutes / 60, 1);
    }
}
