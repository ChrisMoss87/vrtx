<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use App\Domain\Chat\Entities\ChatConversation;

interface ChatConversationRepositoryInterface
{
    public function findById(int $id): ?ChatConversation;
    
    public function findAll(): array;
    
    public function save(ChatConversation $entity): ChatConversation;
    
    public function delete(int $id): bool;
}
