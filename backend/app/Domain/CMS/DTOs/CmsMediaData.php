<?php

declare(strict_types=1);

namespace App\Domain\CMS\DTOs;

final readonly class CmsMediaData
{
    public function __construct(
        public string $name,
        public string $filename,
        public string $path,
        public string $mimeType,
        public int $size,
        public string $type,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $altText = null,
        public ?string $caption = null,
        public ?string $description = null,
        public ?array $metadata = null,
        public ?int $folderId = null,
        public ?array $tags = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            filename: $data['filename'],
            path: $data['path'],
            mimeType: $data['mime_type'],
            size: $data['size'],
            type: $data['type'] ?? 'other',
            width: $data['width'] ?? null,
            height: $data['height'] ?? null,
            altText: $data['alt_text'] ?? null,
            caption: $data['caption'] ?? null,
            description: $data['description'] ?? null,
            metadata: $data['metadata'] ?? null,
            folderId: $data['folder_id'] ?? null,
            tags: $data['tags'] ?? null,
        );
    }
}
