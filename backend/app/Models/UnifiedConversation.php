<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnifiedConversation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'channel',
        'status',
        'subject',
        'contact_name',
        'contact_email',
        'contact_phone',
        'contact_record_id',
        'contact_module_api_name',
        'assigned_to',
        'linked_module_api_name',
        'linked_record_id',
        'source_conversation_id',
        'external_thread_id',
        'tags',
        'metadata',
        'message_count',
        'last_message_at',
        'first_response_at',
        'response_time_seconds',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'message_count' => 'integer',
        'response_time_seconds' => 'integer',
        'last_message_at' => 'datetime',
        'first_response_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(UnifiedMessage::class, 'conversation_id');
    }

    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['open', 'pending']);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeLinkedToRecord($query, string $module, int $recordId)
    {
        return $query->where('linked_module_api_name', $module)
            ->where('linked_record_id', $recordId);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('subject', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('contact_email', 'like', "%{$search}%")
                ->orWhere('contact_phone', 'like', "%{$search}%");
        });
    }
}
