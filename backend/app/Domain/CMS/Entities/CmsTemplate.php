<?php

declare(strict_types=1);

namespace App\Domain\CMS\Entities;

use App\Domain\CMS\ValueObjects\TemplateType;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

final class CmsTemplate implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private string $name,
        private string $slug,
        private ?string $description,
        private TemplateType $type,
        private ?array $content,
        private ?array $settings,
        private ?string $thumbnail,
        private bool $isSystem,
        private bool $isActive,
        private ?int $createdBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    public static function create(
        string $name,
        string $slug,
        TemplateType $type,
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            slug: $slug,
            description: null,
            type: $type,
            content: null,
            settings: null,
            thumbnail: null,
            isSystem: false,
            isActive: true,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        TemplateType $type,
        ?array $content,
        ?array $settings,
        ?string $thumbnail,
        bool $isSystem,
        bool $isActive,
        ?int $createdBy,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            description: $description,
            type: $type,
            content: $content,
            settings: $settings,
            thumbnail: $thumbnail,
            isSystem: $isSystem,
            isActive: $isActive,
            createdBy: $createdBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
    public function getDescription(): ?string { return $this->description; }
    public function getType(): TemplateType { return $this->type; }
    public function getContent(): ?array { return $this->content; }
    public function getSettings(): ?array { return $this->settings; }
    public function getThumbnail(): ?string { return $this->thumbnail; }
    public function isSystem(): bool { return $this->isSystem; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }
    public function getDeletedAt(): ?DateTimeImmutable { return $this->deletedAt; }

    public function update(
        string $name,
        string $slug,
        ?string $description,
        ?array $content,
        ?array $settings,
    ): void {
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->content = $content;
        $this->settings = $settings;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setThumbnail(?string $thumbnail): void
    {
        $this->thumbnail = $thumbnail;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function delete(): void
    {
        if ($this->isSystem) {
            throw new \DomainException('System templates cannot be deleted');
        }
        $this->deletedAt = new DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function duplicate(string $newName, string $newSlug, ?int $createdBy = null): self
    {
        return new self(
            id: null,
            name: $newName,
            slug: $newSlug,
            description: $this->description,
            type: $this->type,
            content: $this->content,
            settings: $this->settings,
            thumbnail: null,
            isSystem: false,
            isActive: true,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }
}
