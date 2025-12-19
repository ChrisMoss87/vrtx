<?php

declare(strict_types=1);

namespace App\Domain\Approval\Repositories;

use App\Domain\Approval\Entities\ApprovalRequest;

interface ApprovalRequestRepositoryInterface
{
    public function findById(int $id): ?ApprovalRequest;

    public function findByUuid(string $uuid): ?ApprovalRequest;

    public function findByRecordId(int $moduleId, int $recordId): array;

    public function findPendingForApprover(int $approverId): array;

    public function findPendingForRecord(int $moduleId, int $recordId): ?ApprovalRequest;

    public function findByEntityType(string $entityType, ?string $status = null): array;

    public function save(ApprovalRequest $request): ApprovalRequest;

    public function delete(int $id): bool;

    public function countPendingForApprover(int $approverId): int;
}
