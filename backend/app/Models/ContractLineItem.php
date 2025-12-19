<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractLineItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'contract_id',
        'product_id',
        'name',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'total',
        'start_date',
        'end_date',
        'display_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'total' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function calculateTotal(): float
    {
        $subtotal = $this->quantity * $this->unit_price;
        $discount = $subtotal * ($this->discount_percent / 100);
        return $subtotal - $discount;
    }

    protected static function booted(): void
    {
        static::saving(function (ContractLineItem $item) {
            $item->total = $item->calculateTotal();
        });
    }
}
