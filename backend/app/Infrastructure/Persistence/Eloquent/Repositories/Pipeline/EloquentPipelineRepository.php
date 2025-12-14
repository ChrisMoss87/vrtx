<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Pipeline;

use App\Domain\Pipeline\Entities\Pipeline;
use App\Domain\Pipeline\Repositories\PipelineRepositoryInterface;
use DateTimeImmutable;

class EloquentPipelineRepository implements PipelineRepositoryInterface
{
    public function findById(int $id): ?Pipeline
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Pipeline $entity): Pipeline
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
