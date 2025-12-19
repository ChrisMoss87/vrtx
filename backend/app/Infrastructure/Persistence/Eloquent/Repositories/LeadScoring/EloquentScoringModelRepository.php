<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\LeadScoring;

use App\Domain\LeadScoring\Entities\ScoringModel;
use App\Domain\LeadScoring\Repositories\ScoringModelRepositoryInterface;
use DateTimeImmutable;

class EloquentScoringModelRepository implements ScoringModelRepositoryInterface
{
    public function findById(int $id): ?ScoringModel
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(ScoringModel $entity): ScoringModel
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
