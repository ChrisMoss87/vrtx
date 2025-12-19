<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealRoomMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'member_id',
        'message',
        'attachments',
        'is_internal',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_internal' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(DealRoom::class, 'room_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(DealRoomMember::class, 'member_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }
}
