<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Repositories;

use App\Domain\Blueprint\Entities\BlueprintRecordState;

interface BlueprintRecordStateRepositoryInterface
{
    public function findById(int $id): ?BlueprintRecordState;

    public function findByRecordId(int $blueprintId, int $recordId): ?BlueprintRecordState;

    public function findByStateId(int $stateId): array;

    public function findByBlueprintId(int $blueprintId): array;

    public function save(BlueprintRecordState $recordState): BlueprintRecordState;

    public function delete(int $id): bool;

    public function deleteByRecordId(int $blueprintId, int $recordId): bool;
}
