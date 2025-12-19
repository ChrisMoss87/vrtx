<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaybookActivity extends Model
{
    protected $fillable = [
        'instance_id',
        'task_instance_id',
        'action',
        'details',
        'user_id',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PlaybookInstance::class, 'instance_id');
    }

    public function taskInstance(): BelongsTo
    {
        return $this->belongsTo(PlaybookTaskInstance::class, 'task_instance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(
        PlaybookInstance $instance,
        string $action,
        array $details = [],
        ?PlaybookTaskInstance $taskInstance = null
    ): self {
        return static::create([
            'instance_id' => $instance->id,
            'task_instance_id' => $taskInstance?->id,
            'action' => $action,
            'details' => $details,
            'user_id' => auth()->id(),
        ]);
    }
}
