<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DealRoomMember extends Model
{
    use HasFactory;

    public const ROLE_OWNER = 'owner';
    public const ROLE_TEAM = 'team';
    public const ROLE_STAKEHOLDER = 'stakeholder';
    public const ROLE_VIEWER = 'viewer';

    protected $fillable = [
        'room_id',
        'user_id',
        'external_email',
        'external_name',
        'role',
        'access_token',
        'token_expires_at',
        'last_accessed_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    public static function getRoles(): array
    {
        return [
            self::ROLE_OWNER => 'Owner',
            self::ROLE_TEAM => 'Team Member',
            self::ROLE_STAKEHOLDER => 'Stakeholder',
            self::ROLE_VIEWER => 'Viewer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(DealRoom::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DealRoomMessage::class, 'member_id');
    }

    public function documentViews(): HasMany
    {
        return $this->hasMany(DealRoomDocumentView::class, 'member_id');
    }

    public function isInternal(): bool
    {
        return $this->user_id !== null;
    }

    public function isExternal(): bool
    {
        return $this->user_id === null;
    }

    public function getName(): string
    {
        if ($this->isInternal()) {
            return $this->user?->name ?? 'Unknown User';
        }
        return $this->external_name ?? $this->external_email ?? 'Unknown';
    }

    public function getEmail(): ?string
    {
        if ($this->isInternal()) {
            return $this->user?->email;
        }
        return $this->external_email;
    }

    public function generateAccessToken(int $expiresInDays = 30): string
    {
        $this->access_token = Str::random(64);
        $this->token_expires_at = now()->addDays($expiresInDays);
        $this->save();

        return $this->access_token;
    }

    public function isTokenValid(): bool
    {
        if (!$this->access_token) {
            return false;
        }

        if ($this->token_expires_at && $this->token_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function recordAccess(): void
    {
        $this->update(['last_accessed_at' => now()]);
    }

    public function canEdit(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_TEAM]);
    }

    public function canManageMembers(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }
}
