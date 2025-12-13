<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxConversation extends Model
{
    protected $fillable = [
        'inbox_id',
        'subject',
        'status',
        'priority',
        'channel',
        'assigned_to',
        'contact_id',
        'contact_email',
        'contact_name',
        'contact_phone',
        'snippet',
        'first_response_at',
        'resolved_at',
        'last_message_at',
        'message_count',
        'response_time_seconds',
        'is_spam',
        'is_starred',
        'tags',
        'custom_fields',
        'external_thread_id',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_message_at' => 'datetime',
        'is_spam' => 'boolean',
        'is_starred' => 'boolean',
        'tags' => 'array',
        'custom_fields' => 'array',
    ];

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'inbox_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InboxMessage::class, 'conversation_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeNotSpam($query)
    {
        return $query->where('is_spam', false);
    }

    public function scopeStarred($query)
    {
        return $query->where('is_starred', true);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'closed']);
    }

    public function assignTo(?int $userId): void
    {
        // Update old assignee's count
        if ($this->assigned_to) {
            $oldMember = SharedInboxMember::where('inbox_id', $this->inbox_id)
                ->where('user_id', $this->assigned_to)
                ->first();
            $oldMember?->decrementActiveCount();
        }

        // Update new assignee's count
        if ($userId) {
            $newMember = SharedInboxMember::where('inbox_id', $this->inbox_id)
                ->where('user_id', $userId)
                ->first();
            $newMember?->incrementActiveCount();
        }

        $this->update(['assigned_to' => $userId]);
    }

    public function resolve(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        // Update assignee's count
        if ($this->assigned_to) {
            $member = SharedInboxMember::where('inbox_id', $this->inbox_id)
                ->where('user_id', $this->assigned_to)
                ->first();
            $member?->decrementActiveCount();
        }
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'open',
            'resolved_at' => null,
        ]);

        // Update assignee's count
        if ($this->assigned_to) {
            $member = SharedInboxMember::where('inbox_id', $this->inbox_id)
                ->where('user_id', $this->assigned_to)
                ->first();
            $member?->incrementActiveCount();
        }
    }

    public function markAsSpam(): void
    {
        $this->update(['is_spam' => true]);
    }

    public function toggleStar(): void
    {
        $this->update(['is_starred' => !$this->is_starred]);
    }

    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $this->update(['tags' => array_values(array_diff($tags, [$tag]))]);
    }

    public function calculateResponseTime(): void
    {
        if ($this->first_response_at && $this->created_at) {
            $this->update([
                'response_time_seconds' => $this->first_response_at->diffInSeconds($this->created_at)
            ]);
        }
    }
}
