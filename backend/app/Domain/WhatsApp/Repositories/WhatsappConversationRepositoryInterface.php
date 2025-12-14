<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Repositories;

use App\Domain\WhatsApp\Entities\WhatsappConversation;

interface WhatsappConversationRepositoryInterface
{
    public function findById(int $id): ?WhatsappConversation;
    
    public function findAll(): array;
    
    public function save(WhatsappConversation $entity): WhatsappConversation;
    
    public function delete(int $id): bool;
}
