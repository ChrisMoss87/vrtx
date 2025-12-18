<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthScoreHistory extends Model
{
    use HasFactory;
    protected $table = 'health_score_history';

    protected $fillable = [
        'customer_health_score_id',
        'overall_score',
        'scores_snapshot',
        'recorded_at',
    ];

    protected $casts = [
        'scores_snapshot' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function healthScore(): BelongsTo
    {
        return $this->belongsTo(CustomerHealthScore::class, 'customer_health_score_id');
    }
}
