<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Call;

use App\Domain\Call\Entities\Call;
use App\Domain\Call\Repositories\CallRepositoryInterface;
use DateTimeImmutable;

class EloquentCallRepository implements CallRepositoryInterface
{
    public function findById(int $id): ?Call
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Call $entity): Call
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
