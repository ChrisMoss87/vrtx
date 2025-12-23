<?php

declare(strict_types=1);

namespace App\Domain\Approval\Repositories;

use App\Domain\Approval\Entities\ApprovalRule;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface ApprovalRuleRepositoryInterface
{
    // =========================================================================
    // ENTITY-BASED METHODS (for domain logic)
    // =========================================================================

    public function findById(int $id): ?ApprovalRule;

    public function findMatchingRule(string $entityType, array $data): ?ApprovalRule;

    public function save(ApprovalRule $rule): ApprovalRule;

    // =========================================================================
    // ARRAY-BASED QUERY METHODS (for application layer)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    public function findByModuleId(int $moduleId): array;

    public function findActiveByModuleId(int $moduleId): array;

    public function findAll(): array;

    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult;

    public function findByEntityType(string $entityType): array;

    public function findByPriority(int $minPriority = 0): array;

    // =========================================================================
    // MUTATION & UTILITY METHODS
    // =========================================================================

    public function delete(int $id): bool;

    public function countByModuleId(int $moduleId): int;

    public function countActive(): int;
}
