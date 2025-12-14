<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Repositories;

use App\Domain\Blueprint\Entities\BlueprintTransition;

interface BlueprintTransitionRepositoryInterface
{
    public function findById(int $id): ?BlueprintTransition;

    public function findByBlueprintId(int $blueprintId): array;

    public function findFromState(int $blueprintId, ?int $fromStateId): array;

    public function findActiveFromState(int $blueprintId, ?int $fromStateId): array;

    public function save(BlueprintTransition $transition): BlueprintTransition;

    public function delete(int $id): bool;

    public function deleteByBlueprintId(int $blueprintId): int;
}
