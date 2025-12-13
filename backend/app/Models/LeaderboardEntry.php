<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderboardEntry extends Model
{
    protected $fillable = [
        'period_id',
        'metric_type',
        'user_id',
        'rank',
        'value',
        'target',
        'attainment_percent',
        'trend',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'target' => 'decimal:2',
        'attainment_percent' => 'decimal:2',
        'trend' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(QuotaPeriod::class, 'period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getGapAttribute(): float
    {
        return $this->target - $this->value;
    }

    public function getRankBadgeAttribute(): ?string
    {
        return match ($this->rank) {
            1 => '🥇',
            2 => '🥈',
            3 => '🥉',
            default => null,
        };
    }
}
