<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatAgentStatus extends Model
{
    protected $table = 'chat_agent_status';

    protected $fillable = [
        'user_id',
        'status',
        'max_conversations',
        'active_conversations',
        'departments',
        'last_activity_at',
    ];

    protected $casts = [
        'departments' => 'array',
        'last_activity_at' => 'datetime',
    ];

    public const STATUS_ONLINE = 'online';
    public const STATUS_AWAY = 'away';
    public const STATUS_BUSY = 'busy';
    public const STATUS_OFFLINE = 'offline';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', self::STATUS_ONLINE);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_ONLINE)
            ->whereRaw('active_conversations < max_conversations');
    }

    public function scopeInDepartment($query, string $department)
    {
        return $query->whereJsonContains('departments', $department);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_ONLINE
            && $this->active_conversations < $this->max_conversations;
    }

    public function canHandleDepartment(?string $department): bool
    {
        if (!$department) {
            return true;
        }

        if (empty($this->departments)) {
            return true; // No department restrictions
        }

        return in_array($department, $this->departments);
    }

    public function setOnline(): void
    {
        $this->update([
            'status' => self::STATUS_ONLINE,
            'last_activity_at' => now(),
        ]);
    }

    public function setAway(): void
    {
        $this->update([
            'status' => self::STATUS_AWAY,
            'last_activity_at' => now(),
        ]);
    }

    public function setBusy(): void
    {
        $this->update([
            'status' => self::STATUS_BUSY,
            'last_activity_at' => now(),
        ]);
    }

    public function setOffline(): void
    {
        $this->update([
            'status' => self::STATUS_OFFLINE,
            'active_conversations' => 0,
        ]);
    }

    public function recordActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public static function getOrCreate(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'status' => self::STATUS_OFFLINE,
                'max_conversations' => 5,
                'active_conversations' => 0,
            ]
        );
    }
}
