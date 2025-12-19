<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealCompetitor extends Model
{
    protected $fillable = [
        'deal_id',
        'competitor_id',
        'is_primary',
        'notes',
        'outcome',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public const OUTCOME_WON = 'won';
    public const OUTCOME_LOST = 'lost';
    public const OUTCOME_UNKNOWN = 'unknown';

    public static function getOutcomes(): array
    {
        return [
            self::OUTCOME_WON => 'Won',
            self::OUTCOME_LOST => 'Lost',
            self::OUTCOME_UNKNOWN => 'Unknown',
        ];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    public function scopeForDeal($query, int $dealId)
    {
        return $query->where('deal_id', $dealId);
    }

    public function scopeWon($query)
    {
        return $query->where('outcome', self::OUTCOME_WON);
    }

    public function scopeLost($query)
    {
        return $query->where('outcome', self::OUTCOME_LOST);
    }

    public function setPrimary(): void
    {
        // Unset other primaries for this deal
        self::where('deal_id', $this->deal_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);
    }

    public function setOutcome(string $outcome): void
    {
        $this->update(['outcome' => $outcome]);
    }
}
