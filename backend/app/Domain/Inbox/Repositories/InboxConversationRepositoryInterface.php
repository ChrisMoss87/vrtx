<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Repositories;

use App\Domain\Inbox\Entities\InboxConversation;

interface InboxConversationRepositoryInterface
{
    public function findById(int $id): ?InboxConversation;
    
    public function findAll(): array;
    
    public function save(InboxConversation $entity): InboxConversation;
    
    public function delete(int $id): bool;
}
