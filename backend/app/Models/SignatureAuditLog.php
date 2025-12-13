<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureAuditLog extends Model
{
    public $timestamps = false;

    public const EVENT_CREATED = 'created';
    public const EVENT_SENT = 'sent';
    public const EVENT_VIEWED = 'viewed';
    public const EVENT_SIGNED = 'signed';
    public const EVENT_DECLINED = 'declined';
    public const EVENT_COMPLETED = 'completed';
    public const EVENT_VOIDED = 'voided';
    public const EVENT_REMINDED = 'reminded';
    public const EVENT_EXPIRED = 'expired';

    protected $fillable = [
        'request_id',
        'signer_id',
        'event_type',
        'event_description',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function request(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class, 'request_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(SignatureSigner::class, 'signer_id');
    }
}
