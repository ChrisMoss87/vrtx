<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Repositories;

use App\Domain\Blueprint\Entities\Blueprint;

interface BlueprintRepositoryInterface
{
    public function findById(int $id): ?Blueprint;

    public function findByModuleId(int $moduleId): array;

    public function findByFieldId(int $fieldId): ?Blueprint;

    public function findActiveForModule(int $moduleId): array;

    public function findAll(): array;

    public function save(Blueprint $blueprint): Blueprint;

    public function delete(int $id): bool;
}
