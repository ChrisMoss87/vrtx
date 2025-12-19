<?php

declare(strict_types=1);

namespace App\Domain\CMS\DTOs;

final readonly class CmsPageData
{
    public function __construct(
        public string $title,
        public string $slug,
        public string $type,
        public ?string $excerpt = null,
        public ?array $content = null,
        public ?int $templateId = null,
        public ?int $parentId = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $metaKeywords = null,
        public ?string $canonicalUrl = null,
        public ?string $ogImage = null,
        public bool $noindex = false,
        public bool $nofollow = false,
        public ?int $featuredImageId = null,
        public ?string $scheduledAt = null,
        public ?array $settings = null,
        public ?array $categoryIds = null,
        public ?array $tagIds = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            slug: $data['slug'],
            type: $data['type'] ?? 'page',
            excerpt: $data['excerpt'] ?? null,
            content: $data['content'] ?? null,
            templateId: $data['template_id'] ?? null,
            parentId: $data['parent_id'] ?? null,
            metaTitle: $data['meta_title'] ?? null,
            metaDescription: $data['meta_description'] ?? null,
            metaKeywords: $data['meta_keywords'] ?? null,
            canonicalUrl: $data['canonical_url'] ?? null,
            ogImage: $data['og_image'] ?? null,
            noindex: $data['noindex'] ?? false,
            nofollow: $data['nofollow'] ?? false,
            featuredImageId: $data['featured_image_id'] ?? null,
            scheduledAt: $data['scheduled_at'] ?? null,
            settings: $data['settings'] ?? null,
            categoryIds: $data['category_ids'] ?? null,
            tagIds: $data['tag_ids'] ?? null,
        );
    }
}
