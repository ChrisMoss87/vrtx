<?php

declare(strict_types=1);

namespace App\Domain\Contract\Repositories;

use App\Domain\Contract\Entities\Contract;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface ContractRepositoryInterface
{
    /**
     * Find a contract by ID (returns domain entity).
     */
    public function findById(int $id): ?Contract;

    /**
     * Save a contract entity (create or update).
     */
    public function save(Contract $contract): Contract;

    /**
     * Find a contract by ID (returns array for backward compatibility).
     */
    public function findByIdAsArray(int $id): ?array;

    /**
     * Find all contracts.
     */
    public function findAll(): array;

    /**
     * List contracts with filtering and pagination.
     */
    public function listContracts(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get contracts for a related record.
     */
    public function getContractsForRecord(string $module, int $recordId): array;

    /**
     * Get contracts expiring within days.
     */
    public function getExpiringContracts(int $withinDays = 30): array;

    /**
     * Get expired contracts.
     */
    public function getExpiredContracts(): array;

    /**
     * Get contract statistics.
     */
    public function getContractStats(?int $ownerId = null): array;

    /**
     * Get renewal pipeline.
     */
    public function getRenewalPipeline(?int $ownerId = null): array;

    /**
     * Get contracts needing renewal attention.
     */
    public function getContractsNeedingRenewalAttention(): array;

    /**
     * Create a new contract.
     */
    public function create(array $data): array;

    /**
     * Update a contract.
     */
    public function update(int $id, array $data): array;

    /**
     * Delete a contract.
     */
    public function delete(int $id): bool;

    /**
     * Generate a contract number.
     */
    public function generateContractNumber(): string;

    /**
     * Add line items to a contract.
     */
    public function addLineItems(int $contractId, array $items): array;

    /**
     * Update a line item.
     */
    public function updateLineItem(int $lineItemId, array $data): array;

    /**
     * Delete a line item.
     */
    public function deleteLineItem(int $lineItemId): bool;

    /**
     * Reorder line items.
     */
    public function reorderLineItems(int $contractId, array $orderedIds): void;

    /**
     * Recalculate contract value from line items.
     */
    public function recalculateContractValue(int $contractId): void;
}
