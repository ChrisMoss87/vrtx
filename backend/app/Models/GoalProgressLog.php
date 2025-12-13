<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalProgressLog extends Model
{
    protected $fillable = [
        'goal_id',
        'log_date',
        'value',
        'change_amount',
        'change_source',
        'source_record_id',
    ];

    protected $casts = [
        'log_date' => 'date',
        'value' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}
