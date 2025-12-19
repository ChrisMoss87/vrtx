<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsFormSubmission extends Model
{
    protected $fillable = [
        'form_id',
        'data',
        'metadata',
        'contact_id',
        'lead_id',
        'source_url',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'data' => 'array',
        'metadata' => 'array',
        'form_id' => 'integer',
        'contact_id' => 'integer',
        'lead_id' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(CmsForm::class, 'form_id');
    }

    public function scopeForForm($query, int $formId)
    {
        return $query->where('form_id', $formId);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeWithContact($query)
    {
        return $query->whereNotNull('contact_id');
    }

    public function scopeWithLead($query)
    {
        return $query->whereNotNull('lead_id');
    }

    public function getFieldValue(string $fieldName): mixed
    {
        return $this->data[$fieldName] ?? null;
    }

    public function hasLinkedRecord(): bool
    {
        return $this->contact_id !== null || $this->lead_id !== null;
    }

    public function linkToContact(int $contactId): void
    {
        $this->update(['contact_id' => $contactId]);
    }

    public function linkToLead(int $leadId): void
    {
        $this->update(['lead_id' => $leadId]);
    }
}
