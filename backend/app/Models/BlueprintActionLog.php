<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlueprintActionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'execution_id',
        'action_id',
        'status',
        'result',
        'executed_at',
    ];

    protected $casts = [
        'execution_id' => 'integer',
        'action_id' => 'integer',
        'result' => 'array',
        'executed_at' => 'datetime',
    ];

    /**
     * Get the execution.
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(BlueprintTransitionExecution::class, 'execution_id');
    }

    /**
     * Get the action.
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(BlueprintTransitionAction::class, 'action_id');
    }

    /**
     * Check if action succeeded.
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if action failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
