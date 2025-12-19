<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'tax_rate',
        'line_total',
        'display_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
        'display_order' => 'integer',
    ];

    protected $attributes = [
        'quantity' => 1,
        'discount_percent' => 0,
        'tax_rate' => 0,
        'display_order' => 0,
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (InvoiceLineItem $item) {
            $item->calculateLineTotal();
        });

        static::saved(function (InvoiceLineItem $item) {
            $item->invoice->recalculateTotals();
        });

        static::deleted(function (InvoiceLineItem $item) {
            $item->invoice->recalculateTotals();
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function calculateLineTotal(): void
    {
        $subtotal = $this->quantity * $this->unit_price;
        $discount = $subtotal * ($this->discount_percent / 100);
        $this->line_total = $subtotal - $discount;
    }

    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    public function getDiscountAmountAttribute(): float
    {
        return $this->subtotal * ($this->discount_percent / 100);
    }

    public function getTaxAmountAttribute(): float
    {
        return ($this->subtotal - $this->discount_amount) * ($this->tax_rate / 100);
    }
}
