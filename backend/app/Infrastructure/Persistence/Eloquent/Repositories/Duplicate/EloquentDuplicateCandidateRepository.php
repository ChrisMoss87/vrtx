<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Duplicate;

use App\Domain\Duplicate\Entities\DuplicateCandidate;
use App\Domain\Duplicate\Repositories\DuplicateCandidateRepositoryInterface;
use DateTimeImmutable;

class EloquentDuplicateCandidateRepository implements DuplicateCandidateRepositoryInterface
{
    public function findById(int $id): ?DuplicateCandidate
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(DuplicateCandidate $entity): DuplicateCandidate
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
