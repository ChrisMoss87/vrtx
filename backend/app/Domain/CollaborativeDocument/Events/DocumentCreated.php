<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Events;

use App\Domain\CollaborativeDocument\Entities\CollaborativeDocument;
use App\Domain\Shared\Events\DomainEvent;

final class DocumentCreated extends DomainEvent
{
    public function __construct(
        private readonly int $documentId,
        private readonly string $title,
        private readonly string $type,
        private readonly int $ownerId,
        private readonly ?int $folderId,
    ) {
        parent::__construct();
    }

    public static function fromDocument(CollaborativeDocument $document): self
    {
        return new self(
            documentId: $document->getId(),
            title: $document->getTitle(),
            type: $document->getType()->value,
            ownerId: $document->getOwnerId(),
            folderId: $document->getParentFolderId(),
        );
    }

    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function getFolderId(): ?int
    {
        return $this->folderId;
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
            'title' => $this->title,
            'type' => $this->type,
            'owner_id' => $this->ownerId,
            'folder_id' => $this->folderId,
        ];
    }
}
