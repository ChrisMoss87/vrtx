<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Repositories;

use App\Domain\Blueprint\Entities\TransitionExecution;

interface TransitionExecutionRepositoryInterface
{
    public function findById(int $id): ?TransitionExecution;

    public function findByRecordId(int $recordId): array;

    public function findByTransitionId(int $transitionId): array;

    public function findPendingForRecord(int $recordId): ?TransitionExecution;

    public function save(TransitionExecution $execution): TransitionExecution;

    public function delete(int $id): bool;
}
