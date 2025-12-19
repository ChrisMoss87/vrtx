<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsPage extends Model
{
    use SoftDeletes;

    public const TYPE_PAGE = 'page';
    public const TYPE_LANDING = 'landing';
    public const TYPE_BLOG = 'blog';
    public const TYPE_ARTICLE = 'article';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'type',
        'status',
        'template_id',
        'parent_id',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'og_image',
        'noindex',
        'nofollow',
        'featured_image_id',
        'published_at',
        'scheduled_at',
        'author_id',
        'created_by',
        'updated_by',
        'settings',
        'view_count',
        'sort_order',
    ];

    protected $casts = [
        'content' => 'array',
        'settings' => 'array',
        'noindex' => 'boolean',
        'nofollow' => 'boolean',
        'view_count' => 'integer',
        'sort_order' => 'integer',
        'template_id' => 'integer',
        'parent_id' => 'integer',
        'featured_image_id' => 'integer',
        'author_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => self::TYPE_PAGE,
        'status' => self::STATUS_DRAFT,
        'noindex' => false,
        'nofollow' => false,
        'view_count' => 0,
        'sort_order' => 0,
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CmsTemplate::class, 'template_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'featured_image_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CmsCategory::class, 'cms_page_category', 'page_id', 'category_id')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CmsTag::class, 'cms_page_tag', 'page_id', 'tag_id')
            ->withTimestamps();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsPageVersion::class, 'page_id')->orderByDesc('version_number');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CmsComment::class, 'page_id');
    }

    public function approvedComments(): HasMany
    {
        return $this->hasMany(CmsComment::class, 'page_id')
            ->where('status', 'approved')
            ->whereNull('parent_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopePages($query)
    {
        return $query->where('type', self::TYPE_PAGE);
    }

    public function scopeBlogPosts($query)
    {
        return $query->where('type', self::TYPE_BLOG);
    }

    public function scopeLandingPages($query)
    {
        return $query->where('type', self::TYPE_LANDING);
    }

    public function scopeByAuthor($query, int $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('excerpt', 'like', "%{$search}%")
              ->orWhere('meta_description', 'like', "%{$search}%");
        });
    }

    public function scopeReadyToPublish($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function publish(): void
    {
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => now(),
            'scheduled_at' => null,
        ]);
    }

    public function unpublish(): void
    {
        $this->update([
            'status' => self::STATUS_DRAFT,
        ]);
    }

    public function schedule(\DateTimeInterface $date): void
    {
        $this->update([
            'status' => self::STATUS_SCHEDULED,
            'scheduled_at' => $date,
        ]);
    }

    public function archive(): void
    {
        $this->update([
            'status' => self::STATUS_ARCHIVED,
        ]);
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function createVersion(?int $userId = null, ?string $summary = null): CmsPageVersion
    {
        $nextVersion = $this->versions()->max('version_number') + 1;

        return $this->versions()->create([
            'version_number' => $nextVersion,
            'title' => $this->title,
            'content' => $this->content ?? [],
            'seo_data' => [
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'meta_keywords' => $this->meta_keywords,
            ],
            'change_summary' => $summary,
            'created_by' => $userId,
        ]);
    }

    public function duplicate(?int $userId = null): self
    {
        $copy = $this->replicate(['view_count', 'published_at', 'scheduled_at']);
        $copy->title = $this->title . ' (Copy)';
        $copy->slug = $this->slug . '-copy-' . time();
        $copy->status = self::STATUS_DRAFT;
        $copy->created_by = $userId ?? $this->created_by;
        $copy->updated_by = $userId;
        $copy->save();

        // Copy categories and tags
        $copy->categories()->sync($this->categories->pluck('id'));
        $copy->tags()->sync($this->tags->pluck('id'));

        return $copy;
    }

    public function getEffectiveTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function getEffectiveDescription(): ?string
    {
        return $this->meta_description ?: $this->excerpt;
    }

    public function getUrl(): string
    {
        return match ($this->type) {
            self::TYPE_BLOG => "/blog/{$this->slug}",
            self::TYPE_LANDING => "/lp/{$this->slug}",
            default => "/{$this->slug}",
        };
    }
}
