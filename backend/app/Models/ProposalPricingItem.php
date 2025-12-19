<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalPricingItem extends Model
{
    public const PRICING_FIXED = 'fixed';
    public const PRICING_RECURRING = 'recurring';
    public const PRICING_USAGE = 'usage';

    public const PRICING_TYPES = [
        self::PRICING_FIXED,
        self::PRICING_RECURRING,
        self::PRICING_USAGE,
    ];

    public const BILLING_MONTHLY = 'monthly';
    public const BILLING_QUARTERLY = 'quarterly';
    public const BILLING_ANNUALLY = 'annually';
    public const BILLING_ONE_TIME = 'one_time';

    protected $fillable = [
        'proposal_id',
        'section_id',
        'name',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_percent',
        'line_total',
        'is_optional',
        'is_selected',
        'pricing_type',
        'billing_frequency',
        'display_order',
        'product_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'line_total' => 'decimal:2',
        'is_optional' => 'boolean',
        'is_selected' => 'boolean',
        'display_order' => 'integer',
    ];

    protected $attributes = [
        'quantity' => 1,
        'unit_price' => 0,
        'discount_percent' => 0,
        'line_total' => 0,
        'is_optional' => false,
        'is_selected' => true,
        'pricing_type' => self::PRICING_FIXED,
        'display_order' => 0,
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (ProposalPricingItem $item) {
            $item->calculateLineTotal();
        });

        static::saved(function (ProposalPricingItem $item) {
            $item->proposal->calculateTotal();
        });

        static::deleted(function (ProposalPricingItem $item) {
            $item->proposal->calculateTotal();
        });
    }

    // Relationships
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ProposalSection::class, 'section_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Scopes
    public function scopeSelected($query)
    {
        return $query->where('is_selected', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_optional', true);
    }

    // Helpers
    public function calculateLineTotal(): void
    {
        $subtotal = $this->quantity * $this->unit_price;
        $discount = $subtotal * ($this->discount_percent / 100);
        $this->line_total = $subtotal - $discount;
    }

    public function toggleSelection(): void
    {
        if ($this->is_optional) {
            $this->is_selected = !$this->is_selected;
            $this->save();
        }
    }
}
