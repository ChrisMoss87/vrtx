<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_prefix',
        'invoice_prefix',
        'quote_next_number',
        'invoice_next_number',
        'quote_validity_days',
        'default_payment_terms',
        'default_tax_rate',
        'currency',
        'company_info',
        'default_terms',
        'default_notes',
    ];

    protected $casts = [
        'quote_next_number' => 'integer',
        'invoice_next_number' => 'integer',
        'quote_validity_days' => 'integer',
        'default_tax_rate' => 'decimal:2',
        'company_info' => 'array',
    ];

    protected $attributes = [
        'quote_prefix' => 'Q',
        'invoice_prefix' => 'INV',
        'quote_next_number' => 1,
        'invoice_next_number' => 1,
        'quote_validity_days' => 30,
        'default_payment_terms' => 'Net 30',
        'default_tax_rate' => 0,
        'currency' => 'USD',
    ];

    public static function getSettings(): self
    {
        return static::first() ?? static::create([]);
    }

    public function generateQuoteNumber(): string
    {
        $number = $this->quote_prefix . '-' . date('Y') . '-' . str_pad((string) $this->quote_next_number, 4, '0', STR_PAD_LEFT);
        $this->increment('quote_next_number');
        return $number;
    }

    public function generateInvoiceNumber(): string
    {
        $number = $this->invoice_prefix . '-' . date('Y') . '-' . str_pad((string) $this->invoice_next_number, 4, '0', STR_PAD_LEFT);
        $this->increment('invoice_next_number');
        return $number;
    }
}
