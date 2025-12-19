<?php

declare(strict_types=1);

namespace App\Domain\Approval\Repositories;

use App\Domain\Approval\Entities\ApprovalRule;

interface ApprovalRuleRepositoryInterface
{
    public function findById(int $id): ?ApprovalRule;

    public function findByModuleId(int $moduleId): array;

    public function findActiveByModuleId(int $moduleId): array;

    public function findMatchingRule(string $entityType, array $data): ?ApprovalRule;

    public function save(ApprovalRule $rule): ApprovalRule;

    public function delete(int $id): bool;
}
