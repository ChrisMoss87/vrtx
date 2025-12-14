<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Contract;

use App\Domain\Contract\Entities\Contract;
use App\Domain\Contract\Repositories\ContractRepositoryInterface;
use DateTimeImmutable;

class EloquentContractRepository implements ContractRepositoryInterface
{
    public function findById(int $id): ?Contract
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Contract $entity): Contract
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
