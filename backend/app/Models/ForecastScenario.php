<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForecastScenario extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_CURRENT = 'current';
    public const TYPE_BEST_CASE = 'best_case';
    public const TYPE_WORST_CASE = 'worst_case';
    public const TYPE_TARGET_HIT = 'target_hit';
    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'module_id',
        'period_start',
        'period_end',
        'scenario_type',
        'is_baseline',
        'is_shared',
        'total_weighted',
        'total_unweighted',
        'target_amount',
        'deal_count',
        'settings',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'is_baseline' => 'boolean',
        'is_shared' => 'boolean',
        'total_weighted' => 'decimal:2',
        'total_unweighted' => 'decimal:2',
        'target_amount' => 'decimal:2',
        'settings' => 'array',
    ];

    public static function getScenarioTypes(): array
    {
        return [
            self::TYPE_CURRENT => 'Current State',
            self::TYPE_BEST_CASE => 'Best Case',
            self::TYPE_WORST_CASE => 'Worst Case',
            self::TYPE_TARGET_HIT => 'Target Hit',
            self::TYPE_CUSTOM => 'Custom',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(ScenarioDeal::class, 'scenario_id');
    }

    public function committedDeals(): HasMany
    {
        return $this->deals()->where('is_committed', true);
    }

    public function activeDeals(): HasMany
    {
        return $this->deals()->where('is_excluded', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere('is_shared', true);
        });
    }

    public function scopeForPeriod($query, string $start, string $end)
    {
        return $query->where('period_start', '<=', $end)
            ->where('period_end', '>=', $start);
    }

    public function scopeBaseline($query)
    {
        return $query->where('is_baseline', true);
    }

    public function recalculateTotals(): self
    {
        $deals = $this->activeDeals()->get();

        $this->deal_count = $deals->count();
        $this->total_unweighted = $deals->sum('amount');
        $this->total_weighted = $deals->sum(function ($deal) {
            $probability = $deal->probability ?? 50;
            return $deal->amount * ($probability / 100);
        });

        $this->save();

        return $this;
    }

    public function getGapAmount(): float
    {
        if (!$this->target_amount) {
            return 0;
        }

        return (float) $this->target_amount - (float) $this->total_weighted;
    }

    public function getProgressPercent(): float
    {
        if (!$this->target_amount || $this->target_amount == 0) {
            return 0;
        }

        return min(100, ((float) $this->total_weighted / (float) $this->target_amount) * 100);
    }
}
