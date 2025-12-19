<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Battlecard extends Model
{
    protected $fillable = [
        'competitor_id',
        'title',
        'sections',
        'talking_points',
        'objection_handlers',
        'is_published',
        'created_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'talking_points' => 'array',
        'objection_handlers' => 'array',
        'is_published' => 'boolean',
    ];

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(BattlecardSection::class, 'competitor_id', 'competitor_id');
    }
}
