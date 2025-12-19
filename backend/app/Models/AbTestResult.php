<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'variant_id',
        'date',
        'impressions',
        'clicks',
        'conversions',
        'opens',
        'revenue',
    ];

    protected $casts = [
        'date' => 'date',
        'revenue' => 'decimal:2',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(AbTestVariant::class, 'variant_id');
    }
}
