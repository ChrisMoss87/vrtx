<?php

declare(strict_types=1);

namespace App\Domain\CMS\Entities;

use App\Domain\CMS\ValueObjects\MediaType;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class CmsMedia implements Entity
{
    private function __construct(
        private ?int $id,
        private string $name,
        private string $filename,
        private string $path,
        private string $disk,
        private string $mimeType,
        private int $size,
        private MediaType $type,
        private ?int $width,
        private ?int $height,
        private ?string $altText,
        private ?string $caption,
        private ?string $description,
        private ?array $metadata,
        private ?int $folderId,
        private ?array $tags,
        private ?int $uploadedBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    public static function create(
        string $name,
        string $filename,
        string $path,
        string $mimeType,
        int $size,
        MediaType $type,
        ?int $uploadedBy = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            filename: $filename,
            path: $path,
            disk: 'public',
            mimeType: $mimeType,
            size: $size,
            type: $type,
            width: null,
            height: null,
            altText: null,
            caption: null,
            description: null,
            metadata: null,
            folderId: null,
            tags: null,
            uploadedBy: $uploadedBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $filename,
        string $path,
        string $disk,
        string $mimeType,
        int $size,
        MediaType $type,
        ?int $width,
        ?int $height,
        ?string $altText,
        ?string $caption,
        ?string $description,
        ?array $metadata,
        ?int $folderId,
        ?array $tags,
        ?int $uploadedBy,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            filename: $filename,
            path: $path,
            disk: $disk,
            mimeType: $mimeType,
            size: $size,
            type: $type,
            width: $width,
            height: $height,
            altText: $altText,
            caption: $caption,
            description: $description,
            metadata: $metadata,
            folderId: $folderId,
            tags: $tags,
            uploadedBy: $uploadedBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getFilename(): string { return $this->filename; }
    public function getPath(): string { return $this->path; }
    public function getDisk(): string { return $this->disk; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSize(): int { return $this->size; }
    public function getType(): MediaType { return $this->type; }
    public function getWidth(): ?int { return $this->width; }
    public function getHeight(): ?int { return $this->height; }
    public function getAltText(): ?string { return $this->altText; }
    public function getCaption(): ?string { return $this->caption; }
    public function getDescription(): ?string { return $this->description; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function getFolderId(): ?int { return $this->folderId; }
    public function getTags(): ?array { return $this->tags; }
    public function getUploadedBy(): ?int { return $this->uploadedBy; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }
    public function getDeletedAt(): ?DateTimeImmutable { return $this->deletedAt; }

    public function setDimensions(int $width, int $height): void
    {
        $this->width = $width;
        $this->height = $height;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateMetadata(
        ?string $altText = null,
        ?string $caption = null,
        ?string $description = null,
    ): void {
        $this->altText = $altText;
        $this->caption = $caption;
        $this->description = $description;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function moveToFolder(?int $folderId): void
    {
        $this->folderId = $folderId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function rename(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
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

    public function getUrl(): string
    {
        return "/storage/{$this->path}";
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->id !== null
            && $other->id !== null
            && $this->id === $other->id;
    }
}
