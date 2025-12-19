<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\WhatsApp;

use App\Domain\WhatsApp\Entities\WhatsappConversation;
use App\Domain\WhatsApp\Repositories\WhatsappConversationRepositoryInterface;
use DateTimeImmutable;

class EloquentWhatsappConversationRepository implements WhatsappConversationRepositoryInterface
{
    public function findById(int $id): ?WhatsappConversation
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(WhatsappConversation $entity): WhatsappConversation
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
