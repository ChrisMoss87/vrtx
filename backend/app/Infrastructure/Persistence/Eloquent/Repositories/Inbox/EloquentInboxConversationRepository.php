<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Inbox;

use App\Domain\Inbox\Entities\InboxConversation;
use App\Domain\Inbox\Repositories\InboxConversationRepositoryInterface;
use DateTimeImmutable;

class EloquentInboxConversationRepository implements InboxConversationRepositoryInterface
{
    public function findById(int $id): ?InboxConversation
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(InboxConversation $entity): InboxConversation
    {
        // TODO: Implement with Eloquent model
        return $entity;
    }

    public function delete(int $id): bool
    {
        // TODO: Implement with Eloquent model
        return false;
    }
}
