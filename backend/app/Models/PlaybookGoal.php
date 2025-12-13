<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaybookGoal extends Model
{
    protected $fillable = [
        'playbook_id',
        'name',
        'metric_type',
        'target_module',
        'target_field',
        'comparison_operator',
        'target_value',
        'target_days',
        'description',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
    ];

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(PlaybookGoalResult::class, 'goal_id');
    }

    public function evaluate(PlaybookInstance $instance, $actualValue): bool
    {
        $target = $this->target_value;

        return match ($this->comparison_operator) {
            '>=' => $actualValue >= $target,
            '<=' => $actualValue <= $target,
            '>' => $actualValue > $target,
            '<' => $actualValue < $target,
            '=' => $actualValue == $target,
            default => false,
        };
    }
}
