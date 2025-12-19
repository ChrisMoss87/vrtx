<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class EmailAccount extends Model
{
    use HasFactory, SoftDeletes;

    // Provider types
    public const PROVIDER_IMAP = 'imap';
    public const PROVIDER_GMAIL = 'gmail';
    public const PROVIDER_OUTLOOK = 'outlook';
    public const PROVIDER_SMTP_ONLY = 'smtp_only';

    protected $fillable = [
        'user_id',
        'name',
        'email_address',
        'provider',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'username',
        'password',
        'oauth_token',
        'oauth_refresh_token',
        'oauth_expires_at',
        'is_active',
        'is_default',
        'sync_enabled',
        'last_sync_at',
        'last_sync_uid',
        'sync_folders',
        'signature',
        'settings',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'imap_port' => 'integer',
        'smtp_port' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sync_enabled' => 'boolean',
        'last_sync_at' => 'datetime',
        'oauth_expires_at' => 'datetime',
        'sync_folders' => 'array',
        'settings' => 'array',
    ];

    protected $hidden = [
        'password',
        'oauth_token',
        'oauth_refresh_token',
    ];

    protected $attributes = [
        'provider' => self::PROVIDER_IMAP,
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'is_active' => true,
        'is_default' => false,
        'sync_enabled' => true,
        'sync_folders' => '["INBOX"]',
        'settings' => '{}',
    ];

    /**
     * Get the user this account belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the emails for this account.
     */
    public function emails(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'account_id');
    }

    /**
     * Encrypt the password before saving.
     */
    public function setPasswordAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt the password when accessing.
     */
    public function getPasswordAttribute(?string $value): ?string
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Encrypt OAuth token before saving.
     */
    public function setOauthTokenAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['oauth_token'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt OAuth token when accessing.
     */
    public function getOauthTokenAttribute(?string $value): ?string
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Check if OAuth token needs refresh.
     */
    public function needsTokenRefresh(): bool
    {
        if (!$this->oauth_expires_at) {
            return false;
        }
        return $this->oauth_expires_at->isPast();
    }

    /**
     * Scope for active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for sync-enabled accounts.
     */
    public function scopeSyncEnabled($query)
    {
        return $query->where('sync_enabled', true)->where('is_active', true);
    }

    /**
     * Get the default account for a user.
     */
    public static function getDefaultForUser(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Make this account the default.
     */
    public function makeDefault(): void
    {
        // Remove default from other accounts
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get IMAP configuration.
     */
    public function getImapConfig(): array
    {
        return [
            'host' => $this->imap_host,
            'port' => $this->imap_port,
            'encryption' => $this->imap_encryption,
            'username' => $this->username,
            'password' => $this->password,
        ];
    }

    /**
     * Get SMTP configuration.
     */
    public function getSmtpConfig(): array
    {
        return [
            'host' => $this->smtp_host,
            'port' => $this->smtp_port,
            'encryption' => $this->smtp_encryption,
            'username' => $this->username,
            'password' => $this->password,
        ];
    }
}
