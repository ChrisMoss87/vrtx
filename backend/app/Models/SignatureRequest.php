<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SignatureRequest extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_VOIDED = 'voided';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_DECLINED,
        self::STATUS_EXPIRED,
        self::STATUS_VOIDED,
    ];

    public const SOURCE_QUOTE = 'quote';
    public const SOURCE_INVOICE = 'invoice';
    public const SOURCE_PROPOSAL = 'proposal';
    public const SOURCE_CUSTOM = 'custom';

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'document_id',
        'source_type',
        'source_id',
        'file_path',
        'file_url',
        'signed_file_path',
        'signed_file_url',
        'status',
        'sent_at',
        'completed_at',
        'expires_at',
        'voided_at',
        'void_reason',
        'settings',
        'external_provider',
        'external_id',
        'created_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (SignatureRequest $request) {
            if (empty($request->uuid)) {
                $request->uuid = Str::uuid()->toString();
            }
        });
    }

    // Relationships
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'document_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(SignatureSigner::class, 'request_id')->orderBy('sign_order');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(SignatureField::class, 'request_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(SignatureAuditLog::class, 'request_id')->orderByDesc('created_at');
    }

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
                     ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_VOIDED]);
    }

    // Helpers
    public function getPublicUrl(): string
    {
        return url("/sign/{$this->uuid}");
    }

    public function send(): void
    {
        $this->status = self::STATUS_PENDING;
        $this->sent_at = now();
        $this->save();

        // Mark first signer as current
        $firstSigner = $this->signers()->orderBy('sign_order')->first();
        if ($firstSigner) {
            $firstSigner->sent_at = now();
            $firstSigner->save();
        }

        $this->logEvent('sent', 'Signature request sent to signers');
    }

    public function complete(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();

        $this->logEvent('completed', 'All signatures collected');
    }

    public function void(string $reason): void
    {
        $this->status = self::STATUS_VOIDED;
        $this->voided_at = now();
        $this->void_reason = $reason;
        $this->save();

        $this->logEvent('voided', "Request voided: {$reason}");
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeVoided(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function getNextSigner(): ?SignatureSigner
    {
        return $this->signers()
            ->where('status', SignatureSigner::STATUS_PENDING)
            ->orderBy('sign_order')
            ->first();
    }

    public function checkCompletion(): bool
    {
        $allSigned = $this->signers()
            ->where('role', 'signer')
            ->where('status', '!=', SignatureSigner::STATUS_SIGNED)
            ->doesntExist();

        if ($allSigned) {
            $this->complete();
            return true;
        }

        return false;
    }

    public function logEvent(string $eventType, ?string $description = null, ?SignatureSigner $signer = null, array $metadata = []): SignatureAuditLog
    {
        return $this->auditLogs()->create([
            'signer_id' => $signer?->id,
            'event_type' => $eventType,
            'event_description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
