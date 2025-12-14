<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Chat;

use App\Domain\Chat\Entities\ChatConversation;
use App\Domain\Chat\Repositories\ChatConversationRepositoryInterface;
use DateTimeImmutable;

class EloquentChatConversationRepository implements ChatConversationRepositoryInterface
{
    public function findById(int $id): ?ChatConversation
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(ChatConversation $entity): ChatConversation
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
