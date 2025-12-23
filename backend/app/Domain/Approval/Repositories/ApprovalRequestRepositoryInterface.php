<?php

declare(strict_types=1);

namespace App\Domain\Approval\Repositories;

use App\Domain\Approval\Entities\ApprovalRequest;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface ApprovalRequestRepositoryInterface
{
    // =========================================================================
    // ENTITY-BASED METHODS (for domain logic)
    // =========================================================================

    public function findById(int $id): ?ApprovalRequest;

    public function findByUuid(string $uuid): ?ApprovalRequest;

    public function save(ApprovalRequest $request): ApprovalRequest;

    // =========================================================================
    // ARRAY-BASED QUERY METHODS (for application layer)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    public function findByUuidAsArray(string $uuid): ?array;

    public function findByRecordId(int $moduleId, int $recordId): array;

    public function findPendingForApprover(int $approverId): array;

    public function findPendingForRecord(int $moduleId, int $recordId): ?array;

    public function findByEntityType(string $entityType, ?string $status = null): array;

    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult;

    public function findByApprover(int $approverId, array $filters = []): array;

    public function findByRequester(int $requesterId, array $filters = []): array;

    public function findByModuleId(int $moduleId, array $filters = []): array;

    // =========================================================================
    // MUTATION & UTILITY METHODS
    // =========================================================================

    public function delete(int $id): bool;

    public function countPendingForApprover(int $approverId): int;

    public function countByStatus(string $status, ?int $approverId = null): int;

    public function getStatsByApprover(int $approverId): array;

    public function getStatsByModule(int $moduleId): array;
}
