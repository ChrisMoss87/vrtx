<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalDocumentShare extends Model
{
    protected $fillable = [
        'portal_user_id',
        'account_id',
        'document_type',
        'document_id',
        'can_download',
        'requires_signature',
        'signed_at',
        'signature_ip',
        'view_count',
        'first_viewed_at',
        'last_viewed_at',
        'expires_at',
        'shared_by',
    ];

    protected $casts = [
        'can_download' => 'boolean',
        'requires_signature' => 'boolean',
        'signed_at' => 'datetime',
        'first_viewed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    public function needsSignature(): bool
    {
        return $this->requires_signature && !$this->isSigned();
    }

    public function recordView(): void
    {
        $this->increment('view_count');

        if (!$this->first_viewed_at) {
            $this->first_viewed_at = now();
        }
        $this->last_viewed_at = now();
        $this->save();
    }

    public function sign(string $ip): void
    {
        $this->update([
            'signed_at' => now(),
            'signature_ip' => $ip,
        ]);
    }

    public static function getDocumentTypes(): array
    {
        return [
            'quote' => 'Quote',
            'invoice' => 'Invoice',
            'contract' => 'Contract',
            'proposal' => 'Proposal',
            'file' => 'File',
        ];
    }
}
