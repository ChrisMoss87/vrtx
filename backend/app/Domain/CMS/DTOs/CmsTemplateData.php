<?php

declare(strict_types=1);

namespace App\Domain\CMS\DTOs;

final readonly class CmsTemplateData
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $type,
        public ?string $description = null,
        public ?array $content = null,
        public ?array $settings = null,
        public ?string $thumbnail = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            slug: $data['slug'],
            type: $data['type'] ?? 'page',
            description: $data['description'] ?? null,
            content: $data['content'] ?? null,
            settings: $data['settings'] ?? null,
            thumbnail: $data['thumbnail'] ?? null,
        );
    }
}
