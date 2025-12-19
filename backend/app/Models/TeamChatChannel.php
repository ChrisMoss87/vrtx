<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamChatChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'channel_id',
        'name',
        'description',
        'is_private',
        'is_archived',
        'member_count',
        'last_activity_at',
        'metadata',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_archived' => 'boolean',
        'metadata' => 'array',
        'last_activity_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TeamChatConnection::class, 'connection_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(TeamChatNotification::class, 'channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TeamChatMessage::class, 'channel_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }
}
