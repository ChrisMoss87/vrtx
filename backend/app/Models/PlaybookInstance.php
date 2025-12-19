<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlaybookInstance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'playbook_id',
        'related_module',
        'related_id',
        'status',
        'started_at',
        'target_completion_at',
        'completed_at',
        'paused_at',
        'owner_id',
        'progress_percent',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'target_completion_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function taskInstances(): HasMany
    {
        return $this->hasMany(PlaybookTaskInstance::class, 'instance_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PlaybookActivity::class, 'instance_id')->orderByDesc('created_at');
    }

    public function goalResults(): HasMany
    {
        return $this->hasMany(PlaybookGoalResult::class, 'instance_id');
    }

    public function getRelatedRecord()
    {
        return ModuleRecord::where('module_api_name', $this->related_module)
            ->where('id', $this->related_id)
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForRecord($query, string $module, int $recordId)
    {
        return $query->where('related_module', $module)
            ->where('related_id', $recordId);
    }

    public function calculateProgress(): int
    {
        $total = $this->taskInstances()->count();
        if ($total === 0) {
            return 0;
        }

        $completed = $this->taskInstances()
            ->whereIn('status', ['completed', 'skipped'])
            ->count();

        return (int) round(($completed / $total) * 100);
    }

    public function updateProgress(): void
    {
        $this->progress_percent = $this->calculateProgress();
        $this->save();
    }

    public function complete(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->progress_percent = 100;
        $this->save();
    }

    public function pause(): void
    {
        $this->status = 'paused';
        $this->paused_at = now();
        $this->save();
    }

    public function resume(): void
    {
        $this->status = 'active';
        $this->paused_at = null;
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = 'cancelled';
        $this->save();
    }

    public function getCompletedTaskCount(): int
    {
        return $this->taskInstances()->where('status', 'completed')->count();
    }

    public function getTotalTaskCount(): int
    {
        return $this->taskInstances()->count();
    }

    public function getOverdueTaskCount(): int
    {
        return $this->taskInstances()
            ->where('status', 'pending')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();
    }
}
