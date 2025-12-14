<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\ImportExport;

use App\Domain\ImportExport\Entities\Import;
use App\Domain\ImportExport\Repositories\ImportRepositoryInterface;
use DateTimeImmutable;

class EloquentImportRepository implements ImportRepositoryInterface
{
    public function findById(int $id): ?Import
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Import $entity): Import
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
