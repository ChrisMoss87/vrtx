<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Repositories;

use App\Domain\Webhook\Entities\Webhook;

interface WebhookRepositoryInterface
{
    public function findById(int $id): ?Webhook;
    
    public function findAll(): array;
    
    public function save(Webhook $entity): Webhook;
    
    public function delete(int $id): bool;
}
