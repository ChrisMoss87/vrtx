<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Events;

use App\Domain\CollaborativeDocument\Entities\CollaborativeDocument;
use App\Domain\CollaborativeDocument\Entities\DocumentVersion;
use App\Domain\Shared\Events\DomainEvent;

final class VersionCreated extends DomainEvent
{
    public function __construct(
        private readonly int $documentId,
        private readonly int $versionId,
        private readonly int $versionNumber,
        private readonly ?string $label,
        private readonly int $createdBy,
        private readonly bool $isAutoSave,
    ) {
        parent::__construct();
    }

    public static function fromVersion(DocumentVersion $version): self
    {
        return new self(
            documentId: $version->getDocumentId(),
            versionId: $version->getId(),
            versionNumber: $version->getVersionNumber(),
            label: $version->getLabel(),
            createdBy: $version->getCreatedBy(),
            isAutoSave: $version->isAutoSave(),
        );
    }

    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    public function getVersionId(): int
    {
        return $this->versionId;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function isAutoSave(): bool
    {
        return $this->isAutoSave;
    }

    public function aggregateId(): int|string
    {
        return $this->documentId;
    }

    public function aggregateType(): string
    {
        return CollaborativeDocument::class;
    }

    public function toPayload(): array
    {
        return [
            'document_id' => $this->documentId,
            'version_id' => $this->versionId,
            'version_number' => $this->versionNumber,
            'label' => $this->label,
            'created_by' => $this->createdBy,
            'is_auto_save' => $this->isAutoSave,
        ];
    }
}
