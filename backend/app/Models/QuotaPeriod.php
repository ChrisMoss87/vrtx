<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotaPeriod extends Model
{
    public const TYPE_MONTH = 'month';
    public const TYPE_QUARTER = 'quarter';
    public const TYPE_YEAR = 'year';
    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'name',
        'period_type',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function quotas(): HasMany
    {
        return $this->hasMany(Quota::class, 'period_id');
    }

    public function leaderboardEntries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class, 'period_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrent($query)
    {
        $today = Carbon::today();
        return $query->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('period_type', $type);
    }

    // Helpers
    public function isCurrent(): bool
    {
        $today = Carbon::today();
        return $this->start_date <= $today && $this->end_date >= $today;
    }

    public function getDaysRemainingAttribute(): int
    {
        $today = Carbon::today();
        if ($today > $this->end_date) {
            return 0;
        }
        return $today->diffInDays($this->end_date);
    }

    public function getDaysTotalAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getDaysElapsedAttribute(): int
    {
        $today = Carbon::today();
        if ($today < $this->start_date) {
            return 0;
        }
        if ($today > $this->end_date) {
            return $this->days_total;
        }
        return $this->start_date->diffInDays($today) + 1;
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->days_total === 0) {
            return 100;
        }
        return round(($this->days_elapsed / $this->days_total) * 100, 1);
    }

    // Static helpers to create common periods
    public static function createMonthPeriod(int $year, int $month): self
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        return self::create([
            'name' => $start->format('F Y'),
            'period_type' => self::TYPE_MONTH,
            'start_date' => $start,
            'end_date' => $end,
            'is_active' => true,
        ]);
    }

    public static function createQuarterPeriod(int $year, int $quarter): self
    {
        $quarterNames = ['Q1', 'Q2', 'Q3', 'Q4'];
        $startMonth = (($quarter - 1) * 3) + 1;

        $start = Carbon::create($year, $startMonth, 1);
        $end = $start->copy()->addMonths(2)->endOfMonth();

        return self::create([
            'name' => $quarterNames[$quarter - 1] . ' ' . $year,
            'period_type' => self::TYPE_QUARTER,
            'start_date' => $start,
            'end_date' => $end,
            'is_active' => true,
        ]);
    }

    public static function createYearPeriod(int $year): self
    {
        return self::create([
            'name' => "FY {$year}",
            'period_type' => self::TYPE_YEAR,
            'start_date' => Carbon::create($year, 1, 1),
            'end_date' => Carbon::create($year, 12, 31),
            'is_active' => true,
        ]);
    }

    public static function getCurrentPeriod(string $type = self::TYPE_QUARTER): ?self
    {
        return self::active()
            ->current()
            ->type($type)
            ->first();
    }
}
