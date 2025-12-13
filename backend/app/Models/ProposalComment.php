<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalComment extends Model
{
    public const AUTHOR_CLIENT = 'client';
    public const AUTHOR_INTERNAL = 'internal';

    protected $fillable = [
        'proposal_id',
        'section_id',
        'comment',
        'author_email',
        'author_name',
        'author_type',
        'reply_to_id',
        'is_resolved',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    protected $attributes = [
        'author_type' => self::AUTHOR_CLIENT,
        'is_resolved' => false,
    ];

    // Relationships
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ProposalSection::class, 'section_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(ProposalComment::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ProposalComment::class, 'reply_to_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeClient($query)
    {
        return $query->where('author_type', self::AUTHOR_CLIENT);
    }

    public function scopeInternal($query)
    {
        return $query->where('author_type', self::AUTHOR_INTERNAL);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('reply_to_id');
    }

    // Helpers
    public function resolve(int $userId): void
    {
        $this->is_resolved = true;
        $this->resolved_by = $userId;
        $this->resolved_at = now();
        $this->save();
    }

    public function unresolve(): void
    {
        $this->is_resolved = false;
        $this->resolved_by = null;
        $this->resolved_at = null;
        $this->save();
    }
}
