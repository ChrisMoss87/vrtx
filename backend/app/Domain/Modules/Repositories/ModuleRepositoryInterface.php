<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories;

use App\Domain\Modules\Entities\Module;

interface ModuleRepositoryInterface
{
    public function findById(int $id): ?Module;

    public function findByApiName(string $apiName): ?Module;

    public function findAll(): array;

    public function findActive(): array;

    public function save(Module $module): Module;

    public function delete(int $id): bool;

    public function existsByName(string $name, ?int $excludeId = null): bool;

    public function existsByApiName(string $apiName, ?int $excludeId = null): bool;
}
