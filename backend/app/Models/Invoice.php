<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_VIEWED,
        self::STATUS_PAID,
        self::STATUS_PARTIAL,
        self::STATUS_OVERDUE,
        self::STATUS_CANCELLED,
    ];

    public const PAYMENT_TERMS = [
        'due_on_receipt' => 'Due on Receipt',
        'net_7' => 'Net 7',
        'net_15' => 'Net 15',
        'net_30' => 'Net 30',
        'net_45' => 'Net 45',
        'net_60' => 'Net 60',
        'net_90' => 'Net 90',
    ];

    protected $fillable = [
        'invoice_number',
        'quote_id',
        'deal_id',
        'contact_id',
        'company_id',
        'status',
        'title',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'amount_paid',
        'balance_due',
        'currency',
        'issue_date',
        'due_date',
        'payment_terms',
        'notes',
        'internal_notes',
        'template_id',
        'view_token',
        'sent_at',
        'sent_to_email',
        'viewed_at',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'currency' => 'USD',
        'amount_paid' => 0,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Invoice $invoice) {
            if (empty($invoice->view_token)) {
                $invoice->view_token = Str::random(32);
            }
        });
    }

    // Relationships
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class)->orderBy('display_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderByDesc('payment_date');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuoteTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now()->startOfDay())
            ->whereNotIn('status', [self::STATUS_PAID, self::STATUS_CANCELLED]);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereNotIn('status', [self::STATUS_PAID, self::STATUS_CANCELLED]);
    }

    // Helpers
    public function getPublicUrl(): string
    {
        return url("/invoice/{$this->view_token}");
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeSent(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_VIEWED, self::STATUS_PARTIAL]);
    }

    public function canRecordPayment(): bool
    {
        return !in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED]);
    }

    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && !in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED]);
    }

    public function recalculateTotals(): void
    {
        $this->subtotal = $this->lineItems->sum('line_total');

        // Tax calculation
        $this->tax_amount = $this->lineItems->sum(function ($item) {
            $lineSubtotal = $item->quantity * $item->unit_price * (1 - $item->discount_percent / 100);
            return $lineSubtotal * ($item->tax_rate / 100);
        });

        $this->total = $this->subtotal - $this->discount_amount + $this->tax_amount;
        $this->balance_due = $this->total - $this->amount_paid;
        $this->save();
    }

    public function recalculatePayments(): void
    {
        $this->amount_paid = $this->payments->sum('amount');
        $this->balance_due = $this->total - $this->amount_paid;

        if ($this->balance_due <= 0) {
            $this->status = self::STATUS_PAID;
            $this->paid_at = now();
        } elseif ($this->amount_paid > 0) {
            $this->status = self::STATUS_PARTIAL;
        }

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

    public function checkOverdue(): void
    {
        if ($this->isOverdue() && !in_array($this->status, [self::STATUS_OVERDUE, self::STATUS_PAID, self::STATUS_CANCELLED])) {
            $this->status = self::STATUS_OVERDUE;
            $this->save();
        }
    }
}
