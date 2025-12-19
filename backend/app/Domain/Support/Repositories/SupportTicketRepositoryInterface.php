<?php

declare(strict_types=1);

namespace App\Domain\Support\Repositories;

use App\Domain\Support\Entities\SupportTicket;

interface SupportTicketRepositoryInterface
{
    public function findById(int $id): ?SupportTicket;
    
    public function findAll(): array;
    
    public function save(SupportTicket $entity): SupportTicket;
    
    public function delete(int $id): bool;
}
