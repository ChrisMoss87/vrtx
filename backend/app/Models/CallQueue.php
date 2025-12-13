<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CallQueue extends Model
{
    protected $fillable = [
        'name',
        'description',
        'provider_id',
        'phone_number',
        'routing_strategy',
        'max_wait_time_seconds',
        'max_queue_size',
        'welcome_message',
        'hold_music_url',
        'voicemail_greeting',
        'voicemail_enabled',
        'business_hours',
        'after_hours_message',
        'is_active',
    ];

    protected $casts = [
        'voicemail_enabled' => 'boolean',
        'business_hours' => 'array',
        'is_active' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CallProvider::class, 'provider_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CallQueueMember::class, 'queue_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'call_queue_members', 'queue_id', 'user_id')
            ->withPivot(['priority', 'is_active', 'status', 'last_call_at', 'calls_handled_today'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getNextAvailableAgent(): ?User
    {
        $members = $this->members()
            ->where('is_active', true)
            ->where('status', 'online')
            ->with('user')
            ->get();

        if ($members->isEmpty()) {
            return null;
        }

        switch ($this->routing_strategy) {
            case 'round_robin':
                // Get member with oldest last call
                $member = $members->sortBy('last_call_at')->first();
                break;

            case 'longest_idle':
                // Get member with longest time since last call
                $member = $members->sortBy('last_call_at')->first();
                break;

            case 'skills_based':
                // Get member with highest priority (skill level)
                $member = $members->sortByDesc('priority')->first();
                break;

            default:
                $member = $members->first();
        }

        return $member?->user;
    }

    public function getOnlineAgentCount(): int
    {
        return $this->members()
            ->where('is_active', true)
            ->where('status', 'online')
            ->count();
    }

    public function isWithinBusinessHours(): bool
    {
        if (!$this->business_hours) {
            return true; // No restrictions
        }

        $now = now();
        $dayOfWeek = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');

        $todayHours = $this->business_hours[$dayOfWeek] ?? null;

        if (!$todayHours || !($todayHours['enabled'] ?? true)) {
            return false;
        }

        $start = $todayHours['start'] ?? '00:00';
        $end = $todayHours['end'] ?? '23:59';

        return $currentTime >= $start && $currentTime <= $end;
    }
}
