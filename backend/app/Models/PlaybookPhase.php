<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaybookPhase extends Model
{
    protected $fillable = [
        'playbook_id',
        'name',
        'description',
        'target_days',
        'display_order',
    ];

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PlaybookTask::class, 'phase_id')->orderBy('display_order');
    }
}
