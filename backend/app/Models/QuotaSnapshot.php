<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotaSnapshot extends Model
{
    use HasFactory;
    protected $fillable = [
        'quota_id',
        'snapshot_date',
        'current_value',
        'attainment_percent',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'current_value' => 'decimal:2',
        'attainment_percent' => 'decimal:2',
    ];

    public function quota(): BelongsTo
    {
        return $this->belongsTo(Quota::class);
    }
}
