<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Playbook;

use App\Domain\Playbook\Entities\Playbook;
use App\Domain\Playbook\Repositories\PlaybookRepositoryInterface;
use DateTimeImmutable;

class EloquentPlaybookRepository implements PlaybookRepositoryInterface
{
    public function findById(int $id): ?Playbook
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Playbook $entity): Playbook
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
