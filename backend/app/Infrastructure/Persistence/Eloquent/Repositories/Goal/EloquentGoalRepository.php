<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Goal;

use App\Domain\Goal\Entities\Goal;
use App\Domain\Goal\Repositories\GoalRepositoryInterface;
use DateTimeImmutable;

class EloquentGoalRepository implements GoalRepositoryInterface
{
    public function findById(int $id): ?Goal
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Goal $entity): Goal
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
