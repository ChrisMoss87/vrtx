<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbTestEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'variant_id',
        'visitor_id',
        'event_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(AbTestVariant::class, 'variant_id');
    }
}
