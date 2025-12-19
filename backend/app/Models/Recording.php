<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recording extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'status',
        'started_at',
        'ended_at',
        'module_id',
        'initial_record_id',
        'workflow_id',
        'description',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public const STATUS_RECORDING = 'recording';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CONVERTED = 'converted';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RecordingStep::class)->orderBy('step_order');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_RECORDING, self::STATUS_PAUSED]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isRecording(): bool
    {
        return $this->status === self::STATUS_RECORDING;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isConverted(): bool
    {
        return $this->status === self::STATUS_CONVERTED;
    }

    public function canAddSteps(): bool
    {
        return in_array($this->status, [self::STATUS_RECORDING]);
    }

    public function pause(): void
    {
        $this->update(['status' => self::STATUS_PAUSED]);
    }

    public function resume(): void
    {
        $this->update(['status' => self::STATUS_RECORDING]);
    }

    public function stop(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'ended_at' => now(),
        ]);
    }

    public function markConverted(int $workflowId): void
    {
        $this->update([
            'status' => self::STATUS_CONVERTED,
            'workflow_id' => $workflowId,
        ]);
    }

    public function getNextStepOrder(): int
    {
        return ($this->steps()->max('step_order') ?? 0) + 1;
    }

    public function getDuration(): ?int
    {
        if (!$this->ended_at) {
            return null;
        }
        return $this->started_at->diffInSeconds($this->ended_at);
    }

    public function getActionCounts(): array
    {
        return $this->steps()
            ->selectRaw('action_type, COUNT(*) as count')
            ->groupBy('action_type')
            ->pluck('count', 'action_type')
            ->toArray();
    }
}
