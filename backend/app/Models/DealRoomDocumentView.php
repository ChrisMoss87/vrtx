<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealRoomDocumentView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'member_id',
        'time_spent_seconds',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(DealRoomDocument::class, 'document_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(DealRoomMember::class, 'member_id');
    }
}
