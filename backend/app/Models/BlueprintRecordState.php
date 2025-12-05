<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlueprintRecordState extends Model
{
    use HasFactory;

    protected $fillable = [
        'blueprint_id',
        'record_id',
        'current_state_id',
        'state_entered_at',
    ];

    protected $casts = [
        'blueprint_id' => 'integer',
        'record_id' => 'integer',
        'current_state_id' => 'integer',
        'state_entered_at' => 'datetime',
    ];

    /**
     * Get the blueprint.
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Get the current state.
     */
    public function currentState(): BelongsTo
    {
        return $this->belongsTo(BlueprintState::class, 'current_state_id');
    }

    /**
     * Get available transitions from the current state.
     */
    public function getAvailableTransitions()
    {
        return BlueprintTransition::where('blueprint_id', $this->blueprint_id)
            ->where('from_state_id', $this->current_state_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get time spent in current state in seconds.
     */
    public function getTimeInState(): int
    {
        if (!$this->state_entered_at) {
            return 0;
        }

        return now()->diffInSeconds($this->state_entered_at);
    }

    /**
     * Get time spent in current state in hours.
     */
    public function getTimeInStateHours(): float
    {
        return $this->getTimeInState() / 3600;
    }
}
