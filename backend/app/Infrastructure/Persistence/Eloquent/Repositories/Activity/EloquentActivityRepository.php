<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Activity;

use App\Domain\Activity\Entities\Activity;
use App\Domain\Activity\Repositories\ActivityRepositoryInterface;
use DateTimeImmutable;

class EloquentActivityRepository implements ActivityRepositoryInterface
{
    public function findById(int $id): ?Activity
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Activity $entity): Activity
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
