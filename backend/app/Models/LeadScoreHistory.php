<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadScoreHistory extends Model
{
    protected $table = 'lead_score_history';

    protected $fillable = [
        'lead_score_id',
        'score',
        'grade',
        'change_reason',
    ];

    public function leadScore(): BelongsTo
    {
        return $this->belongsTo(LeadScore::class, 'lead_score_id');
    }
}
