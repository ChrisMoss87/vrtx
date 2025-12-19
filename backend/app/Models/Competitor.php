<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competitor extends Model
{
    protected $fillable = [
        'name',
        'website',
        'logo_url',
        'description',
        'market_position',
        'pricing_info',
        'last_updated_at',
        'last_updated_by',
        'is_active',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(BattlecardSection::class)->orderBy('display_order');
    }

    public function objections(): HasMany
    {
        return $this->hasMany(CompetitorObjection::class)->orderByDesc('effectiveness_score');
    }

    public function dealCompetitors(): HasMany
    {
        return $this->hasMany(DealCompetitor::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CompetitorNote::class)->orderByDesc('created_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
              ->orWhere('description', 'ilike', "%{$search}%");
        });
    }

    public function getWinRate(): ?float
    {
        $deals = $this->dealCompetitors()->whereIn('outcome', ['won', 'lost'])->get();

        if ($deals->isEmpty()) {
            return null;
        }

        $won = $deals->where('outcome', 'won')->count();
        return round(($won / $deals->count()) * 100, 1);
    }

    public function getTotalDeals(): int
    {
        return $this->dealCompetitors()->count();
    }

    public function getWonDeals(): int
    {
        return $this->dealCompetitors()->where('outcome', 'won')->count();
    }

    public function getLostDeals(): int
    {
        return $this->dealCompetitors()->where('outcome', 'lost')->count();
    }

    public function getSectionByType(string $type): ?BattlecardSection
    {
        return $this->sections()->where('section_type', $type)->first();
    }

    public function markUpdated(int $userId): void
    {
        $this->update([
            'last_updated_at' => now(),
            'last_updated_by' => $userId,
        ]);
    }
}
