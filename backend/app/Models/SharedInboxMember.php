<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedInboxMember extends Model
{
    protected $fillable = [
        'inbox_id',
        'user_id',
        'role',
        'can_reply',
        'can_assign',
        'can_close',
        'receives_notifications',
        'active_conversation_limit',
        'current_active_count',
    ];

    protected $casts = [
        'can_reply' => 'boolean',
        'can_assign' => 'boolean',
        'can_close' => 'boolean',
        'receives_notifications' => 'boolean',
    ];

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'inbox_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canHandleMore(): bool
    {
        if ($this->active_conversation_limit === null) {
            return true;
        }

        return $this->current_active_count < $this->active_conversation_limit;
    }

    public function incrementActiveCount(): void
    {
        $this->increment('current_active_count');
    }

    public function decrementActiveCount(): void
    {
        if ($this->current_active_count > 0) {
            $this->decrement('current_active_count');
        }
    }
}
