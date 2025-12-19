<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaybookTask extends Model
{
    protected $fillable = [
        'playbook_id',
        'phase_id',
        'title',
        'description',
        'task_type',
        'task_config',
        'due_days',
        'duration_estimate',
        'is_required',
        'is_milestone',
        'assignee_type',
        'assignee_id',
        'assignee_role',
        'dependencies',
        'checklist',
        'resources',
        'display_order',
    ];

    protected $casts = [
        'task_config' => 'array',
        'dependencies' => 'array',
        'checklist' => 'array',
        'resources' => 'array',
        'is_required' => 'boolean',
        'is_milestone' => 'boolean',
    ];

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(PlaybookPhase::class, 'phase_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(PlaybookTaskInstance::class, 'task_id');
    }

    public function getDependentTasks(): array
    {
        if (empty($this->dependencies)) {
            return [];
        }

        return PlaybookTask::whereIn('id', $this->dependencies)->get()->all();
    }
}
