<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SharedInbox extends Model
{
    protected $fillable = [
        'name',
        'email',
        'description',
        'type',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'username',
        'password',
        'is_active',
        'is_connected',
        'last_synced_at',
        'settings',
        'default_assignee_id',
        'assignment_method',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_connected' => 'boolean',
        'last_synced_at' => 'datetime',
        'settings' => 'array',
        'password' => 'encrypted',
    ];

    protected $hidden = [
        'password',
        'username',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(SharedInboxMember::class, 'inbox_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shared_inbox_members', 'inbox_id', 'user_id')
            ->withPivot(['role', 'can_reply', 'can_assign', 'can_close', 'receives_notifications'])
            ->withTimestamps();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(InboxConversation::class, 'inbox_id');
    }

    public function cannedResponses(): HasMany
    {
        return $this->hasMany(InboxCannedResponse::class, 'inbox_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(InboxRule::class, 'inbox_id');
    }

    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeConnected($query)
    {
        return $query->where('is_connected', true);
    }

    public function getNextAssignee(): ?User
    {
        if ($this->assignment_method === 'manual') {
            return $this->defaultAssignee;
        }

        $members = $this->members()
            ->where('can_reply', true)
            ->with('user')
            ->get();

        if ($members->isEmpty()) {
            return $this->defaultAssignee;
        }

        if ($this->assignment_method === 'round_robin') {
            // Get member with oldest last assignment
            $member = $members->sortBy(function ($m) {
                return $m->user?->last_assignment_at ?? now()->subYears(10);
            })->first();

            return $member?->user;
        }

        if ($this->assignment_method === 'load_balanced') {
            // Get member with lowest active conversation count
            $member = $members
                ->filter(function ($m) {
                    return $m->active_conversation_limit === null
                        || $m->current_active_count < $m->active_conversation_limit;
                })
                ->sortBy('current_active_count')
                ->first();

            return $member?->user;
        }

        return $this->defaultAssignee;
    }

    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function getAutoReplyEnabled(): bool
    {
        return $this->getSetting('auto_reply.enabled', false);
    }

    public function getAutoReplyMessage(): ?string
    {
        return $this->getSetting('auto_reply.message');
    }

    public function getSignature(): ?string
    {
        return $this->getSetting('signature');
    }
}
