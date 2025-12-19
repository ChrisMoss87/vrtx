<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Cadence;

use App\Domain\Cadence\Entities\Cadence;
use App\Domain\Cadence\Repositories\CadenceRepositoryInterface;
use DateTimeImmutable;

class EloquentCadenceRepository implements CadenceRepositoryInterface
{
    public function findById(int $id): ?Cadence
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Cadence $entity): Cadence
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
