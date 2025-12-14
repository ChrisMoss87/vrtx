<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Repositories;

use App\Domain\Blueprint\Entities\BlueprintState;

interface BlueprintStateRepositoryInterface
{
    public function findById(int $id): ?BlueprintState;

    public function findByBlueprintId(int $blueprintId): array;

    public function findInitialState(int $blueprintId): ?BlueprintState;

    public function findByFieldOptionValue(int $blueprintId, string $value): ?BlueprintState;

    public function save(BlueprintState $state): BlueprintState;

    public function delete(int $id): bool;

    public function deleteByBlueprintId(int $blueprintId): int;
}
