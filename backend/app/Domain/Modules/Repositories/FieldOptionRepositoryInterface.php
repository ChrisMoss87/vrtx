<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories;

use App\Domain\Modules\Entities\FieldOption;

interface FieldOptionRepositoryInterface
{
    public function findById(int $id): ?FieldOption;

    /**
     * @return FieldOption[]
     */
    public function findByFieldId(int $fieldId): array;

    public function save(FieldOption $option): FieldOption;

    public function delete(int $id): bool;

    public function deleteByFieldId(int $fieldId): int;
}
