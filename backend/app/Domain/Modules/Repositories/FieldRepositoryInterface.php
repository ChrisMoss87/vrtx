<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories;

use App\Domain\Modules\Entities\Field;

interface FieldRepositoryInterface
{
    public function findById(int $id): ?Field;

    public function findByModuleId(int $moduleId): array;

    public function findByBlockId(int $blockId): array;

    public function save(Field $field): Field;

    public function delete(int $id): bool;

    public function existsByApiName(int $moduleId, string $apiName, ?int $excludeId = null): bool;
}
