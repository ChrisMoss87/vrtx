<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = [
        'widget_id',
        'visitor_id',
        'contact_id',
        'assigned_to',
        'status',
        'priority',
        'department',
        'subject',
        'tags',
        'message_count',
        'visitor_message_count',
        'agent_message_count',
        'rating',
        'rating_comment',
        'first_response_at',
        'resolved_at',
        'last_message_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'rating' => 'decimal:1',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public function widget(): BelongsTo
    {
        return $this->belongsTo(ChatWidget::class, 'widget_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(ChatVisitor::class, 'visitor_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'contact_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function assign(int $userId): void
    {
        $this->update(['assigned_to' => $userId]);

        // Update agent status
        ChatAgentStatus::where('user_id', $userId)->increment('active_conversations');
    }

    public function unassign(): void
    {
        $previousAgent = $this->assigned_to;
        $this->update(['assigned_to' => null]);

        if ($previousAgent) {
            ChatAgentStatus::where('user_id', $previousAgent)
                ->where('active_conversations', '>', 0)
                ->decrement('active_conversations');
        }
    }

    public function close(): void
    {
        $previousAgent = $this->assigned_to;

        $this->update([
            'status' => self::STATUS_CLOSED,
            'resolved_at' => now(),
        ]);

        if ($previousAgent) {
            ChatAgentStatus::where('user_id', $previousAgent)
                ->where('active_conversations', '>', 0)
                ->decrement('active_conversations');
        }
    }

    public function reopen(): void
    {
        $this->update([
            'status' => self::STATUS_OPEN,
            'resolved_at' => null,
        ]);

        if ($this->assigned_to) {
            ChatAgentStatus::where('user_id', $this->assigned_to)->increment('active_conversations');
        }
    }

    public function addMessage(string $content, string $senderType, ?int $senderId = null, array $options = []): ChatMessage
    {
        $message = $this->messages()->create([
            'content' => $content,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'content_type' => $options['content_type'] ?? 'text',
            'attachments' => $options['attachments'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'is_internal' => $options['is_internal'] ?? false,
        ]);

        // Update conversation stats
        $updates = [
            'message_count' => $this->message_count + 1,
            'last_message_at' => now(),
        ];

        if ($senderType === 'visitor') {
            $updates['visitor_message_count'] = $this->visitor_message_count + 1;
        } elseif ($senderType === 'agent') {
            $updates['agent_message_count'] = $this->agent_message_count + 1;

            // Track first response time
            if (!$this->first_response_at) {
                $updates['first_response_at'] = now();
            }
        }

        $this->update($updates);

        return $message;
    }

    public function getFirstResponseTimeMinutes(): ?int
    {
        if (!$this->first_response_at) {
            return null;
        }

        return $this->created_at->diffInMinutes($this->first_response_at);
    }

    public function getResolutionTimeMinutes(): ?int
    {
        if (!$this->resolved_at) {
            return null;
        }

        return $this->created_at->diffInMinutes($this->resolved_at);
    }
}
