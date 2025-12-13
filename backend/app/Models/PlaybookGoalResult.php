<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaybookGoalResult extends Model
{
    protected $fillable = [
        'instance_id',
        'goal_id',
        'actual_value',
        'achieved',
        'achieved_at',
    ];

    protected $casts = [
        'actual_value' => 'decimal:2',
        'achieved' => 'boolean',
        'achieved_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PlaybookInstance::class, 'instance_id');
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(PlaybookGoal::class, 'goal_id');
    }
}
