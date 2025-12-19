<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use HasFactory;

    public const METHOD_CREDIT_CARD = 'credit_card';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CHECK = 'check';
    public const METHOD_CASH = 'cash';
    public const METHOD_OTHER = 'other';

    public const METHODS = [
        self::METHOD_CREDIT_CARD => 'Credit Card',
        self::METHOD_BANK_TRANSFER => 'Bank Transfer',
        self::METHOD_CHECK => 'Check',
        self::METHOD_CASH => 'Cash',
        self::METHOD_OTHER => 'Other',
    ];

    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saved(function (InvoicePayment $payment) {
            $payment->invoice->recalculatePayments();
        });

        static::deleted(function (InvoicePayment $payment) {
            $payment->invoice->recalculatePayments();
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->payment_method] ?? $this->payment_method;
    }
}
