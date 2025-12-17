<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsComment extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SPAM = 'spam';
    public const STATUS_TRASH = 'trash';

    protected $fillable = [
        'page_id',
        'parent_id',
        'user_id',
        'author_name',
        'author_email',
        'author_url',
        'content',
        'status',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'page_id' => 'integer',
        'parent_id' => 'integer',
        'user_id' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'page_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeSpam($query)
    {
        return $query->where('status', self::STATUS_SPAM);
    }

    public function scopeRootComments($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForPage($query, int $pageId)
    {
        return $query->where('page_id', $pageId);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function getDisplayName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        return $this->author_name ?? 'Anonymous';
    }

    public function getDisplayEmail(): ?string
    {
        if ($this->user) {
            return $this->user->email;
        }
        return $this->author_email;
    }

    public function approve(): void
    {
        $this->update(['status' => self::STATUS_APPROVED]);
    }

    public function markAsSpam(): void
    {
        $this->update(['status' => self::STATUS_SPAM]);
    }

    public function trash(): void
    {
        $this->update(['status' => self::STATUS_TRASH]);
    }

    public function restore(): void
    {
        $this->update(['status' => self::STATUS_PENDING]);
    }
}
