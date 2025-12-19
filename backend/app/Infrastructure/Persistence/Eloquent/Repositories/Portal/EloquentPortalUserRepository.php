<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Portal;

use App\Domain\Portal\Entities\PortalUser;
use App\Domain\Portal\Repositories\PortalUserRepositoryInterface;
use DateTimeImmutable;

class EloquentPortalUserRepository implements PortalUserRepositoryInterface
{
    public function findById(int $id): ?PortalUser
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(PortalUser $entity): PortalUser
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
