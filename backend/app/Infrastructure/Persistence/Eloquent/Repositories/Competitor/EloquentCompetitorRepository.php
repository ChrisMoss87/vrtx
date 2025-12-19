<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Competitor;

use App\Domain\Competitor\Entities\Competitor;
use App\Domain\Competitor\Repositories\CompetitorRepositoryInterface;
use DateTimeImmutable;

class EloquentCompetitorRepository implements CompetitorRepositoryInterface
{
    public function findById(int $id): ?Competitor
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Competitor $entity): Competitor
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
