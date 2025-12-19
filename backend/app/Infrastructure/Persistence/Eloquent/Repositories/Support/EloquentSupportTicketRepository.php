<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Support;

use App\Domain\Support\Entities\SupportTicket;
use App\Domain\Support\Repositories\SupportTicketRepositoryInterface;
use DateTimeImmutable;

class EloquentSupportTicketRepository implements SupportTicketRepositoryInterface
{
    public function findById(int $id): ?SupportTicket
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(SupportTicket $entity): SupportTicket
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
