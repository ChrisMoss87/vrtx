<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Proposal;

use App\Domain\Proposal\Entities\Proposal;
use App\Domain\Proposal\Repositories\ProposalRepositoryInterface;
use DateTimeImmutable;

class EloquentProposalRepository implements ProposalRepositoryInterface
{
    public function findById(int $id): ?Proposal
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Proposal $entity): Proposal
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
