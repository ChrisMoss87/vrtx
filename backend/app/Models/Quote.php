<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Quote extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_VIEWED,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'quote_number',
        'deal_id',
        'contact_id',
        'company_id',
        'status',
        'title',
        'subtotal',
        'discount_amount',
        'discount_type',
        'discount_percent',
        'tax_amount',
        'total',
        'currency',
        'valid_until',
        'terms',
        'notes',
        'internal_notes',
        'template_id',
        'version',
        'view_token',
        'accepted_at',
        'accepted_by',
        'accepted_signature',
        'accepted_ip',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'viewed_at',
        'sent_at',
        'sent_to_email',
        'created_by',
        'assigned_to',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'valid_until' => 'date',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'viewed_at' => 'datetime',
        'sent_at' => 'datetime',
        'version' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'currency' => 'USD',
        'discount_type' => 'fixed',
        'version' => 1,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Quote $quote) {
            if (empty($quote->view_token)) {
                $quote->view_token = Str::random(32);
            }
        });
    }

    // Relationships
    public function lineItems(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class)->orderBy('display_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class)->orderByDesc('version');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuoteTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    // Helpers
    public function getPublicUrl(): string
    {
        return url("/quote/{$this->view_token}");
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT]);
    }

    public function canBeSent(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_VIEWED]);
    }

    public function canBeAccepted(): bool
    {
        if ($this->status === self::STATUS_ACCEPTED || $this->status === self::STATUS_REJECTED) {
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function recalculateTotals(): void
    {
        $subtotal = $this->lineItems->sum('line_total');
        $this->subtotal = $subtotal;

        // Calculate discount
        if ($this->discount_type === 'percent' && $this->discount_percent > 0) {
            $this->discount_amount = $subtotal * ($this->discount_percent / 100);
        }

        // Tax is calculated on subtotal minus discount
        $taxableAmount = $subtotal - $this->discount_amount;
        $this->tax_amount = $this->lineItems->sum(function ($item) {
            $lineSubtotal = $item->quantity * $item->unit_price * (1 - $item->discount_percent / 100);
            return $lineSubtotal * ($item->tax_rate / 100);
        });

        $this->total = $subtotal - $this->discount_amount + $this->tax_amount;
        $this->save();
    }

    public function markAsViewed(): void
    {
        if (!$this->viewed_at) {
            $this->viewed_at = now();
            if ($this->status === self::STATUS_SENT) {
                $this->status = self::STATUS_VIEWED;
            }
            $this->save();
        }
    }

    public function accept(string $acceptedBy, ?string $signature = null, ?string $ip = null): void
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->accepted_at = now();
        $this->accepted_by = $acceptedBy;
        $this->accepted_signature = $signature;
        $this->accepted_ip = $ip;
        $this->save();
    }

    public function reject(string $rejectedBy, ?string $reason = null): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejected_at = now();
        $this->rejected_by = $rejectedBy;
        $this->rejection_reason = $reason;
        $this->save();
    }
}
