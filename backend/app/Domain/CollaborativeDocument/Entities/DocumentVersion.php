<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Entities;

use App\Domain\CollaborativeDocument\ValueObjects\DocumentContent;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

final class DocumentVersion implements Entity
{
    private function __construct(
        private ?int $id,
        private int $documentId,
        private int $versionNumber,
        private ?string $label,
        private DocumentContent $content,
        private int $createdBy,
        private bool $isAutoSave,
        private ?DateTimeImmutable $createdAt,
    ) {}

    /**
     * Create an auto-save version.
     */
    public static function createAutoSave(
        int $documentId,
        int $versionNumber,
        DocumentContent $content,
        int $createdBy,
    ): self {
        if ($documentId <= 0) {
            throw new InvalidArgumentException('Document ID must be positive');
        }

        if ($versionNumber <= 0) {
            throw new InvalidArgumentException('Version number must be positive');
        }

        if ($createdBy <= 0) {
            throw new InvalidArgumentException('Created by ID must be positive');
        }

        return new self(
            id: null,
            documentId: $documentId,
            versionNumber: $versionNumber,
            label: null,
            content: $content,
            createdBy: $createdBy,
            isAutoSave: true,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * Create a named version (manual save point).
     */
    public static function createNamedVersion(
        int $documentId,
        int $versionNumber,
        string $label,
        DocumentContent $content,
        int $createdBy,
    ): self {
        if ($documentId <= 0) {
            throw new InvalidArgumentException('Document ID must be positive');
        }

        if ($versionNumber <= 0) {
            throw new InvalidArgumentException('Version number must be positive');
        }

        $label = trim($label);
        if (empty($label)) {
            throw new InvalidArgumentException('Version label cannot be empty');
        }

        if (mb_strlen($label) > 255) {
            throw new InvalidArgumentException('Version label cannot exceed 255 characters');
        }

        if ($createdBy <= 0) {
            throw new InvalidArgumentException('Created by ID must be positive');
        }

        return new self(
            id: null,
            documentId: $documentId,
            versionNumber: $versionNumber,
            label: $label,
            content: $content,
            createdBy: $createdBy,
            isAutoSave: false,
            createdAt: new DateTimeImmutable(),
        );
    }

    public static function reconstitute(
        int $id,
        int $documentId,
        int $versionNumber,
        ?string $label,
        DocumentContent $content,
        int $createdBy,
        bool $isAutoSave,
        ?DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            documentId: $documentId,
            versionNumber: $versionNumber,
            label: $label,
            content: $content,
            createdBy: $createdBy,
            isAutoSave: $isAutoSave,
            createdAt: $createdAt,
        );
    }

    /**
     * Add or update the label for this version.
     */
    public function updateLabel(string $label): self
    {
        $label = trim($label);
        if (empty($label)) {
            throw new InvalidArgumentException('Version label cannot be empty');
        }

        if (mb_strlen($label) > 255) {
            throw new InvalidArgumentException('Version label cannot exceed 255 characters');
        }

        if ($this->label === $label) {
            return $this;
        }

        $new = clone $this;
        $new->label = $label;
        $new->isAutoSave = false;

        return $new;
    }

    public function isNamedVersion(): bool
    {
        return $this->label !== null && !$this->isAutoSave;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getContent(): DocumentContent
    {
        return $this->content;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function isAutoSave(): bool
    {
        return $this->isAutoSave;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if ($this->id === null || $other->id === null) {
            return false;
        }

        return $this->id === $other->id;
    }
}
