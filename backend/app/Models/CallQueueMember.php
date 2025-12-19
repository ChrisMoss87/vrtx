<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallQueueMember extends Model
{
    protected $fillable = [
        'queue_id',
        'user_id',
        'priority',
        'is_active',
        'status',
        'last_call_at',
        'calls_handled_today',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_call_at' => 'datetime',
    ];

    public function queue(): BelongsTo
    {
        return $this->belongsTo(CallQueue::class, 'queue_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeAvailable($query)
    {
        return $query->active()->online();
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->status === 'online';
    }

    public function setStatus(string $status): void
    {
        $this->update(['status' => $status]);
    }

    public function goOnline(): void
    {
        $this->setStatus('online');
    }

    public function goOffline(): void
    {
        $this->setStatus('offline');
    }

    public function setBusy(): void
    {
        $this->setStatus('busy');
    }

    public function setBreak(): void
    {
        $this->setStatus('break');
    }

    public function recordCall(): void
    {
        $this->update([
            'last_call_at' => now(),
            'calls_handled_today' => $this->calls_handled_today + 1,
        ]);
    }

    public function resetDailyStats(): void
    {
        $this->update(['calls_handled_today' => 0]);
    }
}
