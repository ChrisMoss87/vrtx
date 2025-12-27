<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Events;

use App\Domain\CollaborativeDocument\Entities\CollaborativeDocument;
use App\Domain\Shared\Events\DomainEvent;

final class DocumentShared extends DomainEvent
{
    public function __construct(
        private readonly int $documentId,
        private readonly int $sharedWithUserId,
        private readonly string $permission,
        private readonly int $sharedByUserId,
    ) {
        parent::__construct();
    }

    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    public function getSharedWithUserId(): int
    {
        return $this->sharedWithUserId;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }

    public function getSharedByUserId(): int
    {
        return $this->sharedByUserId;
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
            'shared_with_user_id' => $this->sharedWithUserId,
            'permission' => $this->permission,
            'shared_by_user_id' => $this->sharedByUserId,
        ];
    }
}
