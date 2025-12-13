<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObjectionFeedback extends Model
{
    protected $table = 'objection_feedback';

    protected $fillable = [
        'objection_id',
        'deal_id',
        'was_successful',
        'feedback',
        'created_by',
    ];

    protected $casts = [
        'was_successful' => 'boolean',
    ];

    public function objection(): BelongsTo
    {
        return $this->belongsTo(CompetitorObjection::class, 'objection_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::created(function (ObjectionFeedback $feedback) {
            $feedback->objection->recordUsage($feedback->was_successful);
        });
    }
}
