<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SignatureSigner extends Model
{
    use HasFactory;
    public const STATUS_PENDING = 'pending';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_DECLINED = 'declined';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VIEWED,
        self::STATUS_SIGNED,
        self::STATUS_DECLINED,
    ];

    public const ROLE_SIGNER = 'signer';
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_APPROVER = 'approver';
    public const ROLE_CC = 'cc';

    public const ROLES = [
        self::ROLE_SIGNER,
        self::ROLE_VIEWER,
        self::ROLE_APPROVER,
        self::ROLE_CC,
    ];

    protected $fillable = [
        'request_id',
        'email',
        'name',
        'role',
        'sign_order',
        'status',
        'access_token',
        'sent_at',
        'viewed_at',
        'signed_at',
        'declined_at',
        'decline_reason',
        'signed_ip',
        'signed_user_agent',
        'signature_data',
        'contact_id',
    ];

    protected $casts = [
        'sign_order' => 'integer',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'declined_at' => 'datetime',
        'signature_data' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'role' => self::ROLE_SIGNER,
        'sign_order' => 1,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (SignatureSigner $signer) {
            if (empty($signer->access_token)) {
                $signer->access_token = Str::random(64);
            }
        });
    }

    // Relationships
    public function request(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class, 'request_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(SignatureField::class, 'signer_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'contact_id');
    }

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSigners($query)
    {
        return $query->where('role', self::ROLE_SIGNER);
    }

    // Helpers
    public function getSigningUrl(): string
    {
        return url("/sign/{$this->request->uuid}?token={$this->access_token}");
    }

    public function markAsViewed(): void
    {
        if (!$this->viewed_at) {
            $this->viewed_at = now();
            if ($this->status === self::STATUS_PENDING) {
                $this->status = self::STATUS_VIEWED;
            }
            $this->save();

            $this->request->logEvent('viewed', "{$this->name} viewed the document", $this);
        }
    }

    public function sign(array $signatureData, ?string $ip = null, ?string $userAgent = null): void
    {
        $this->status = self::STATUS_SIGNED;
        $this->signed_at = now();
        $this->signature_data = $signatureData;
        $this->signed_ip = $ip;
        $this->signed_user_agent = $userAgent;
        $this->save();

        $this->request->logEvent('signed', "{$this->name} signed the document", $this, [
            'ip' => $ip,
        ]);

        // Check if all signers have signed
        $this->request->checkCompletion();
    }

    public function decline(string $reason): void
    {
        $this->status = self::STATUS_DECLINED;
        $this->declined_at = now();
        $this->decline_reason = $reason;
        $this->save();

        $this->request->status = SignatureRequest::STATUS_DECLINED;
        $this->request->save();

        $this->request->logEvent('declined', "{$this->name} declined to sign: {$reason}", $this);
    }

    public function canSign(): bool
    {
        // Check if it's this signer's turn (for sequential signing)
        $previousSigners = $this->request->signers()
            ->where('sign_order', '<', $this->sign_order)
            ->where('role', self::ROLE_SIGNER)
            ->get();

        foreach ($previousSigners as $signer) {
            if ($signer->status !== self::STATUS_SIGNED) {
                return false;
            }
        }

        return $this->status !== self::STATUS_SIGNED &&
               $this->status !== self::STATUS_DECLINED &&
               $this->request->status !== SignatureRequest::STATUS_VOIDED &&
               $this->request->status !== SignatureRequest::STATUS_COMPLETED;
    }

    public function regenerateToken(): void
    {
        $this->access_token = Str::random(64);
        $this->save();
    }
}
