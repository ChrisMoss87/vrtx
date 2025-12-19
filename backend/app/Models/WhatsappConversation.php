<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WhatsappConversation extends Model
{
    protected $fillable = [
        'connection_id',
        'contact_wa_id',
        'contact_phone',
        'contact_name',
        'module_record_id',
        'module_api_name',
        'status',
        'assigned_to',
        'is_resolved',
        'last_message_at',
        'last_incoming_at',
        'last_outgoing_at',
        'unread_count',
        'metadata',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'last_incoming_at' => 'datetime',
        'last_outgoing_at' => 'datetime',
    ];

    protected $appends = ['display_name'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class, 'connection_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'conversation_id');
    }

    public function moduleRecord(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'module_api_name', 'module_record_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeWithUnread($query)
    {
        return $query->where('unread_count', '>', 0);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->contact_name ?: $this->contact_phone;
    }

    public function markAsRead(): void
    {
        $this->update(['unread_count' => 0]);
        $this->messages()->whereNull('read_at')->where('direction', 'inbound')->update(['read_at' => now()]);
    }

    public function incrementUnread(): void
    {
        $this->increment('unread_count');
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'is_resolved' => true,
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'open',
            'is_resolved' => false,
        ]);
    }

    public function assign(int $userId): void
    {
        $this->update(['assigned_to' => $userId]);
    }

    public function linkToRecord(string $moduleApiName, int $recordId): void
    {
        $this->update([
            'module_api_name' => $moduleApiName,
            'module_record_id' => $recordId,
        ]);
    }

    public function canReceiveMessages(): bool
    {
        // WhatsApp has a 24-hour messaging window after last customer message
        return $this->last_incoming_at && $this->last_incoming_at->gt(now()->subHours(24));
    }
}
