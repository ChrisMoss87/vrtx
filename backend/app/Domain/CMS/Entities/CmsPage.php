<?php

declare(strict_types=1);

namespace App\Domain\CMS\Entities;

use App\Domain\CMS\ValueObjects\PageStatus;
use App\Domain\CMS\ValueObjects\PageType;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

final class CmsPage implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private string $title,
        private string $slug,
        private ?string $excerpt,
        private ?array $content,
        private PageType $type,
        private PageStatus $status,
        private ?int $templateId,
        private ?int $parentId,
        private ?string $metaTitle,
        private ?string $metaDescription,
        private ?string $metaKeywords,
        private ?string $canonicalUrl,
        private ?string $ogImage,
        private bool $noindex,
        private bool $nofollow,
        private ?int $featuredImageId,
        private ?DateTimeImmutable $publishedAt,
        private ?DateTimeImmutable $scheduledAt,
        private ?int $authorId,
        private ?int $createdBy,
        private ?int $updatedBy,
        private ?array $settings,
        private int $viewCount,
        private int $sortOrder,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    public static function create(
        string $title,
        string $slug,
        PageType $type,
        ?int $authorId = null,
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            title: $title,
            slug: $slug,
            excerpt: null,
            content: null,
            type: $type,
            status: PageStatus::DRAFT,
            templateId: null,
            parentId: null,
            metaTitle: null,
            metaDescription: null,
            metaKeywords: null,
            canonicalUrl: null,
            ogImage: null,
            noindex: false,
            nofollow: false,
            featuredImageId: null,
            publishedAt: null,
            scheduledAt: null,
            authorId: $authorId,
            createdBy: $createdBy,
            updatedBy: null,
            settings: null,
            viewCount: 0,
            sortOrder: 0,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $title,
        string $slug,
        ?string $excerpt,
        ?array $content,
        PageType $type,
        PageStatus $status,
        ?int $templateId,
        ?int $parentId,
        ?string $metaTitle,
        ?string $metaDescription,
        ?string $metaKeywords,
        ?string $canonicalUrl,
        ?string $ogImage,
        bool $noindex,
        bool $nofollow,
        ?int $featuredImageId,
        ?DateTimeImmutable $publishedAt,
        ?DateTimeImmutable $scheduledAt,
        ?int $authorId,
        ?int $createdBy,
        ?int $updatedBy,
        ?array $settings,
        int $viewCount,
        int $sortOrder,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            title: $title,
            slug: $slug,
            excerpt: $excerpt,
            content: $content,
            type: $type,
            status: $status,
            templateId: $templateId,
            parentId: $parentId,
            metaTitle: $metaTitle,
            metaDescription: $metaDescription,
            metaKeywords: $metaKeywords,
            canonicalUrl: $canonicalUrl,
            ogImage: $ogImage,
            noindex: $noindex,
            nofollow: $nofollow,
            featuredImageId: $featuredImageId,
            publishedAt: $publishedAt,
            scheduledAt: $scheduledAt,
            authorId: $authorId,
            createdBy: $createdBy,
            updatedBy: $updatedBy,
            settings: $settings,
            viewCount: $viewCount,
            sortOrder: $sortOrder,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getSlug(): string { return $this->slug; }
    public function getExcerpt(): ?string { return $this->excerpt; }
    public function getContent(): ?array { return $this->content; }
    public function getType(): PageType { return $this->type; }
    public function getStatus(): PageStatus { return $this->status; }
    public function getTemplateId(): ?int { return $this->templateId; }
    public function getParentId(): ?int { return $this->parentId; }
    public function getMetaTitle(): ?string { return $this->metaTitle; }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function getMetaKeywords(): ?string { return $this->metaKeywords; }
    public function getCanonicalUrl(): ?string { return $this->canonicalUrl; }
    public function getOgImage(): ?string { return $this->ogImage; }
    public function isNoindex(): bool { return $this->noindex; }
    public function isNofollow(): bool { return $this->nofollow; }
    public function getFeaturedImageId(): ?int { return $this->featuredImageId; }
    public function getPublishedAt(): ?DateTimeImmutable { return $this->publishedAt; }
    public function getScheduledAt(): ?DateTimeImmutable { return $this->scheduledAt; }
    public function getAuthorId(): ?int { return $this->authorId; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function getUpdatedBy(): ?int { return $this->updatedBy; }
    public function getSettings(): ?array { return $this->settings; }
    public function getViewCount(): int { return $this->viewCount; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }
    public function getDeletedAt(): ?DateTimeImmutable { return $this->deletedAt; }

    public function update(
        string $title,
        string $slug,
        ?string $excerpt,
        ?array $content,
        ?int $updatedBy = null,
    ): void {
        $this->title = $title;
        $this->slug = $slug;
        $this->excerpt = $excerpt;
        $this->content = $content;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateSeo(
        ?string $metaTitle,
        ?string $metaDescription,
        ?string $metaKeywords,
        ?string $canonicalUrl,
        ?string $ogImage,
        bool $noindex,
        bool $nofollow,
    ): void {
        $this->metaTitle = $metaTitle;
        $this->metaDescription = $metaDescription;
        $this->metaKeywords = $metaKeywords;
        $this->canonicalUrl = $canonicalUrl;
        $this->ogImage = $ogImage;
        $this->noindex = $noindex;
        $this->nofollow = $nofollow;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setTemplate(?int $templateId): void
    {
        $this->templateId = $templateId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setFeaturedImage(?int $imageId): void
    {
        $this->featuredImageId = $imageId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setParent(?int $parentId): void
    {
        $this->parentId = $parentId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function publish(?int $updatedBy = null): void
    {
        if (!$this->status->canPublish()) {
            throw new \DomainException("Cannot publish page with status: {$this->status->value}");
        }
        $this->status = PageStatus::PUBLISHED;
        $this->publishedAt = new DateTimeImmutable();
        $this->scheduledAt = null;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function schedule(DateTimeImmutable $scheduledAt, ?int $updatedBy = null): void
    {
        $this->status = PageStatus::SCHEDULED;
        $this->scheduledAt = $scheduledAt;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function unpublish(?int $updatedBy = null): void
    {
        $this->status = PageStatus::DRAFT;
        $this->publishedAt = null;
        $this->scheduledAt = null;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function submitForReview(?int $updatedBy = null): void
    {
        $this->status = PageStatus::PENDING_REVIEW;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function archive(?int $updatedBy = null): void
    {
        $this->status = PageStatus::ARCHIVED;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function incrementViewCount(): void
    {
        $this->viewCount++;
    }

    public function delete(): void
    {
        $this->deletedAt = new DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function duplicate(string $newTitle, string $newSlug, ?int $createdBy = null): self
    {
        return new self(
            id: null,
            title: $newTitle,
            slug: $newSlug,
            excerpt: $this->excerpt,
            content: $this->content,
            type: $this->type,
            status: PageStatus::DRAFT,
            templateId: $this->templateId,
            parentId: null,
            metaTitle: null,
            metaDescription: null,
            metaKeywords: null,
            canonicalUrl: null,
            ogImage: null,
            noindex: false,
            nofollow: false,
            featuredImageId: $this->featuredImageId,
            publishedAt: null,
            scheduledAt: null,
            authorId: $createdBy,
            createdBy: $createdBy,
            updatedBy: null,
            settings: $this->settings,
            viewCount: 0,
            sortOrder: 0,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === PageStatus::DRAFT;
    }
}
