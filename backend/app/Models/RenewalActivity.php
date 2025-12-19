<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalActivity extends Model
{
    use HasFactory;
    protected $fillable = [
        'renewal_id',
        'type',
        'subject',
        'description',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function renewal(): BelongsTo
    {
        return $this->belongsTo(Renewal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
