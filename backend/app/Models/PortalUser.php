<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class PortalUser extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'email',
        'password',
        'name',
        'phone',
        'avatar',
        'contact_id',
        'contact_module',
        'account_id',
        'status',
        'email_verified_at',
        'verification_token',
        'last_login_at',
        'last_login_ip',
        'preferences',
        'timezone',
        'locale',
        'two_factor_enabled',
        'two_factor_secret',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'verification_token',
    ];

    protected $casts = [
        'preferences' => 'array',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
    ];

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(PortalAccessToken::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(PortalActivityLog::class);
    }

    public function documentShares(): HasMany
    {
        return $this->hasMany(PortalDocumentShare::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PortalNotification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->notifications()->whereNull('read_at');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'verification_token' => null,
        ]);
    }

    public function suspend(): void
    {
        $this->update(['status' => self::STATUS_SUSPENDED]);
    }

    public function recordLogin(string $ip): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);

        $this->logActivity('login', null, null, ['ip' => $ip]);
    }

    public function logActivity(string $action, ?string $resourceType = null, ?int $resourceId = null, array $metadata = []): PortalActivityLog
    {
        return $this->activityLogs()->create([
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function createToken(string $name, array $abilities = ['*'], ?\DateTime $expiresAt = null): PortalAccessToken
    {
        $plainToken = bin2hex(random_bytes(32));

        $token = $this->accessTokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainToken),
            'abilities' => json_encode($abilities),
            'expires_at' => $expiresAt,
        ]);

        $token->plainTextToken = $plainToken;

        return $token;
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_SUSPENDED => 'Suspended',
        ];
    }
}
