<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\WebForm;

use App\Domain\WebForm\Entities\WebForm;
use App\Domain\WebForm\Repositories\WebFormRepositoryInterface;
use DateTimeImmutable;

class EloquentWebFormRepository implements WebFormRepositoryInterface
{
    public function findById(int $id): ?WebForm
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(WebForm $entity): WebForm
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
