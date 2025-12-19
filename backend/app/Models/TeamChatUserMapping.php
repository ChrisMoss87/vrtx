<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamChatUserMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'user_id',
        'external_user_id',
        'external_username',
        'external_email',
        'is_verified',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TeamChatConnection::class, 'connection_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeForConnection($query, int $connectionId)
    {
        return $query->where('connection_id', $connectionId);
    }

    /**
     * Get mention format for this user based on provider
     */
    public function getMention(): string
    {
        $provider = $this->connection->provider;

        if ($provider === 'slack') {
            return "<@{$this->external_user_id}>";
        }

        if ($provider === 'teams') {
            return "<at>{$this->external_username}</at>";
        }

        return $this->external_username ?? '';
    }

    /**
     * Find user mapping by CRM user ID
     */
    public static function findByUser(int $connectionId, int $userId): ?self
    {
        return static::where('connection_id', $connectionId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Find user mapping by external user ID
     */
    public static function findByExternalId(int $connectionId, string $externalUserId): ?self
    {
        return static::where('connection_id', $connectionId)
            ->where('external_user_id', $externalUserId)
            ->first();
    }
}
