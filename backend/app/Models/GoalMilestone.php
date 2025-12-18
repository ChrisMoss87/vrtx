<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalMilestone extends Model
{
    use HasFactory;
    protected $fillable = [
        'goal_id',
        'name',
        'target_value',
        'target_date',
        'is_achieved',
        'achieved_at',
        'display_order',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'target_date' => 'date',
        'is_achieved' => 'boolean',
        'achieved_at' => 'datetime',
    ];

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    public function markAchieved(): void
    {
        $this->is_achieved = true;
        $this->achieved_at = now();
        $this->save();
    }
}
