<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Plugin;

use App\Domain\Plugin\Entities\Plugin;
use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use DateTimeImmutable;

class EloquentPluginRepository implements PluginRepositoryInterface
{
    public function findById(int $id): ?Plugin
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(Plugin $entity): Plugin
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
