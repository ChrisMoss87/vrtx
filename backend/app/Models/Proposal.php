<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Proposal extends Model
{
    use HasFactory, SoftDeletes;

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
        'uuid',
        'name',
        'proposal_number',
        'template_id',
        'deal_id',
        'contact_id',
        'company_id',
        'status',
        'cover_page',
        'styling',
        'total_value',
        'currency',
        'valid_until',
        'sent_at',
        'sent_to_email',
        'first_viewed_at',
        'last_viewed_at',
        'view_count',
        'total_time_spent',
        'accepted_at',
        'accepted_by',
        'accepted_signature',
        'accepted_ip',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'created_by',
        'assigned_to',
        'version',
    ];

    protected $casts = [
        'cover_page' => 'array',
        'styling' => 'array',
        'total_value' => 'decimal:2',
        'valid_until' => 'datetime',
        'sent_at' => 'datetime',
        'first_viewed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'view_count' => 'integer',
        'total_time_spent' => 'integer',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'version' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'currency' => 'USD',
        'view_count' => 0,
        'total_time_spent' => 0,
        'version' => 1,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Proposal $proposal) {
            if (empty($proposal->uuid)) {
                $proposal->uuid = Str::uuid()->toString();
            }
        });
    }

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(ProposalTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ProposalSection::class)->orderBy('display_order');
    }

    public function pricingItems(): HasMany
    {
        return $this->hasMany(ProposalPricingItem::class)->orderBy('display_order');
    }

    public function views(): HasMany
    {
        return $this->hasMany(ProposalView::class)->orderByDesc('started_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProposalComment::class)->orderByDesc('created_at');
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

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_SENT, self::STATUS_VIEWED]);
    }

    public function scopeForDeal($query, int $dealId)
    {
        return $query->where('deal_id', $dealId);
    }

    // Helpers
    public function getPublicUrl(): string
    {
        return url("/proposal/{$this->uuid}");
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeSent(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_VIEWED]);
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function send(string $email): void
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = now();
        $this->sent_to_email = $email;
        $this->save();
    }

    public function recordView(string $sessionId, ?string $email = null, ?string $name = null): ProposalView
    {
        // Update proposal view tracking
        if (!$this->first_viewed_at) {
            $this->first_viewed_at = now();
            if ($this->status === self::STATUS_SENT) {
                $this->status = self::STATUS_VIEWED;
            }
        }
        $this->last_viewed_at = now();
        $this->view_count++;
        $this->save();

        // Create view record
        return $this->views()->create([
            'viewer_email' => $email,
            'viewer_name' => $name,
            'session_id' => $sessionId,
            'started_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
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

    public function calculateTotal(): void
    {
        $total = $this->pricingItems()
            ->where('is_selected', true)
            ->sum('line_total');

        $this->total_value = $total;
        $this->save();
    }

    public function duplicate(int $userId): self
    {
        $copy = $this->replicate(['uuid', 'status', 'sent_at', 'sent_to_email',
            'first_viewed_at', 'last_viewed_at', 'view_count', 'total_time_spent',
            'accepted_at', 'accepted_by', 'accepted_signature', 'accepted_ip',
            'rejected_at', 'rejected_by', 'rejection_reason']);

        $copy->uuid = Str::uuid()->toString();
        $copy->name = $this->name . ' (Copy)';
        $copy->status = self::STATUS_DRAFT;
        $copy->created_by = $userId;
        $copy->version = 1;
        $copy->save();

        // Duplicate sections
        foreach ($this->sections as $section) {
            $copy->sections()->create($section->toArray());
        }

        // Duplicate pricing items
        foreach ($this->pricingItems as $item) {
            $copy->pricingItems()->create($item->toArray());
        }

        return $copy;
    }
}
