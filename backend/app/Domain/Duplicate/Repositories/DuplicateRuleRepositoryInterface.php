<?php

declare(strict_types=1);

namespace App\Domain\Duplicate\Repositories;

interface DuplicateRuleRepositoryInterface
{
    /**
     * Find a rule by ID.
     */
    public function findById(int $id): ?array;

    /**
     * List rules with filters.
     */
    public function listRules(array $filters = []): array;

    /**
     * Get active rules for a module.
     */
    public function getActiveRulesForModule(int $moduleId): array;

    /**
     * Create a new rule.
     */
    public function create(array $data): array;

    /**
     * Update a rule.
     */
    public function update(int $id, array $data): ?array;

    /**
     * Delete a rule.
     */
    public function delete(int $id): bool;

    /**
     * Toggle rule active status.
     */
    public function toggleActive(int $id): ?array;
}
