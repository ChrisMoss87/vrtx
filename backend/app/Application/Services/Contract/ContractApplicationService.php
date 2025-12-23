<?php

declare(strict_types=1);

namespace App\Application\Services\Contract;

use App\Domain\Contract\Repositories\ContractRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\DB;

class ContractApplicationService
{
    public function __construct(
        private ContractRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - CONTRACTS
    // =========================================================================

    /**
     * List contracts with filtering and pagination.
     */
    public function listContracts(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        return $this->repository->listContracts($filters, $perPage, $page);
    }

    /**
     * Get a single contract by ID.
     */
    public function getContract(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Get contracts for a related record.
     */
    public function getContractsForRecord(string $module, int $recordId): array
    {
        return $this->repository->getContractsForRecord($module, $recordId);
    }

    /**
     * Get contracts expiring soon.
     */
    public function getExpiringContracts(int $withinDays = 30): array
    {
        return $this->repository->getExpiringContracts($withinDays);
    }

    /**
     * Get expired contracts.
     */
    public function getExpiredContracts(): array
    {
        return $this->repository->getExpiredContracts();
    }

    /**
     * Get contract statistics.
     */
    public function getContractStats(?int $ownerId = null): array
    {
        return $this->repository->getContractStats($ownerId);
    }

    /**
     * Get renewal pipeline.
     */
    public function getRenewalPipeline(?int $ownerId = null): array
    {
        return $this->repository->getRenewalPipeline($ownerId);
    }

    // =========================================================================
    // COMMAND USE CASES - CONTRACTS
    // =========================================================================

    /**
     * Create a new contract.
     */
    public function createContract(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $contractData = [
                'name' => $data['name'],
                'contract_number' => $data['contract_number'] ?? $this->repository->generateContractNumber(),
                'related_module' => $data['related_module'] ?? null,
                'related_id' => $data['related_id'] ?? null,
                'type' => $data['type'] ?? 'service',
                'status' => $data['status'] ?? 'draft',
                'value' => $data['value'] ?? 0,
                'currency' => $data['currency'] ?? 'USD',
                'billing_frequency' => $data['billing_frequency'] ?? 'monthly',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'renewal_date' => $data['renewal_date'] ?? null,
                'renewal_notice_days' => $data['renewal_notice_days'] ?? 30,
                'auto_renew' => $data['auto_renew'] ?? false,
                'renewal_status' => $data['renewal_status'] ?? null,
                'owner_id' => $data['owner_id'] ?? $this->authContext->userId(),
                'terms' => $data['terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'custom_fields' => $data['custom_fields'] ?? [],
            ];

            $contract = $this->repository->create($contractData);

            // Add line items if provided
            if (!empty($data['line_items'])) {
                $this->addLineItems($contract['id'], $data['line_items']);
                $this->repository->recalculateContractValue($contract['id']);
                // Refresh contract data
                $contract = $this->repository->findById($contract['id']);
            }

            return $contract;
        });
    }

    /**
     * Update a contract.
     */
    public function updateContract(int $id, array $data): array
    {
        $contract = $this->repository->findById($id);

        if (!$contract) {
            throw new \RuntimeException("Contract not found");
        }

        $updateData = [
            'name' => $data['name'] ?? $contract['name'],
            'related_module' => $data['related_module'] ?? $contract['related_module'],
            'related_id' => $data['related_id'] ?? $contract['related_id'],
            'type' => $data['type'] ?? $contract['type'],
            'status' => $data['status'] ?? $contract['status'],
            'value' => $data['value'] ?? $contract['value'],
            'currency' => $data['currency'] ?? $contract['currency'],
            'billing_frequency' => $data['billing_frequency'] ?? $contract['billing_frequency'],
            'start_date' => $data['start_date'] ?? $contract['start_date'],
            'end_date' => $data['end_date'] ?? $contract['end_date'],
            'renewal_date' => $data['renewal_date'] ?? $contract['renewal_date'],
            'renewal_notice_days' => $data['renewal_notice_days'] ?? $contract['renewal_notice_days'],
            'auto_renew' => $data['auto_renew'] ?? $contract['auto_renew'],
            'renewal_status' => $data['renewal_status'] ?? $contract['renewal_status'],
            'owner_id' => $data['owner_id'] ?? $contract['owner_id'],
            'terms' => $data['terms'] ?? $contract['terms'],
            'notes' => $data['notes'] ?? $contract['notes'],
            'custom_fields' => array_merge($contract['custom_fields'] ?? [], $data['custom_fields'] ?? []),
        ];

        return $this->repository->update($id, $updateData);
    }

    /**
     * Delete a contract.
     */
    public function deleteContract(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Activate a contract.
     */
    public function activateContract(int $id): array
    {
        $contract = $this->repository->findById($id);

        if (!$contract) {
            throw new \RuntimeException("Contract not found");
        }

        if ($contract['status'] === 'active') {
            return $contract;
        }

        return $this->repository->update($id, ['status' => 'active']);
    }

    /**
     * Terminate a contract.
     */
    public function terminateContract(int $id, ?string $reason = null): array
    {
        $contract = $this->repository->findById($id);

        if (!$contract) {
            throw new \RuntimeException("Contract not found");
        }

        $notes = $reason
            ? ($contract['notes'] ? $contract['notes'] . "\n\nTermination reason: " . $reason : "Termination reason: " . $reason)
            : $contract['notes'];

        return $this->repository->update($id, [
            'status' => 'terminated',
            'end_date' => now()->format('Y-m-d'),
            'notes' => $notes,
        ]);
    }

    /**
     * Renew a contract.
     */
    public function renewContract(int $id, array $renewalData = []): array
    {
        $original = $this->repository->findById($id);

        if (!$original) {
            throw new \RuntimeException("Contract not found");
        }

        return DB::transaction(function () use ($original, $renewalData) {
            // Mark original as renewed
            $this->repository->update($original['id'], [
                'status' => 'renewed',
                'renewal_status' => 'completed',
            ]);

            // Calculate new dates
            $startDate = new \DateTime($original['start_date']);
            $endDate = new \DateTime($original['end_date']);
            $duration = $startDate->diff($endDate)->days;
            $newStartDate = $renewalData['start_date'] ?? (clone $endDate)->modify('+1 day')->format('Y-m-d');
            $newEndDate = $renewalData['end_date'] ?? (new \DateTime($newStartDate))->modify("+{$duration} days")->format('Y-m-d');

            // Create new contract
            $newContractData = [
                'name' => $original['name'],
                'contract_number' => $this->repository->generateContractNumber(),
                'related_module' => $original['related_module'],
                'related_id' => $original['related_id'],
                'type' => $original['type'],
                'status' => 'active',
                'value' => $renewalData['value'] ?? $original['value'],
                'currency' => $original['currency'],
                'billing_frequency' => $original['billing_frequency'],
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                'renewal_notice_days' => $original['renewal_notice_days'],
                'auto_renew' => $renewalData['auto_renew'] ?? $original['auto_renew'],
                'owner_id' => $original['owner_id'],
                'terms' => $renewalData['terms'] ?? $original['terms'],
                'custom_fields' => $original['custom_fields'],
            ];

            $newContract = $this->repository->create($newContractData);

            // Copy line items
            if (!empty($original['line_items'])) {
                $lineItems = [];
                foreach ($original['line_items'] as $item) {
                    $lineItems[] = [
                        'product_id' => $item['product_id'] ?? null,
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $renewalData['adjust_prices'] ?? false
                            ? $item['unit_price'] * (1 + ($renewalData['price_adjustment'] ?? 0) / 100)
                            : $item['unit_price'],
                        'discount_percent' => $item['discount_percent'] ?? 0,
                    ];
                }
                $this->repository->addLineItems($newContract['id'], $lineItems);
            }

            // Recalculate value
            $this->repository->recalculateContractValue($newContract['id']);

            return $this->repository->findById($newContract['id']);
        });
    }

    /**
     * Duplicate a contract as draft.
     */
    public function duplicateContract(int $id): array
    {
        $original = $this->repository->findById($id);

        if (!$original) {
            throw new \RuntimeException("Contract not found");
        }

        return DB::transaction(function () use ($original) {
            $newContractData = [
                'name' => $original['name'] . ' (Copy)',
                'contract_number' => $this->repository->generateContractNumber(),
                'related_module' => $original['related_module'],
                'related_id' => $original['related_id'],
                'type' => $original['type'],
                'status' => 'draft',
                'value' => $original['value'],
                'currency' => $original['currency'],
                'billing_frequency' => $original['billing_frequency'],
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->addYear()->format('Y-m-d'),
                'renewal_notice_days' => $original['renewal_notice_days'],
                'auto_renew' => $original['auto_renew'],
                'owner_id' => $this->authContext->userId(),
                'terms' => $original['terms'],
                'custom_fields' => $original['custom_fields'],
            ];

            $newContract = $this->repository->create($newContractData);

            // Copy line items
            if (!empty($original['line_items'])) {
                $lineItems = [];
                foreach ($original['line_items'] as $item) {
                    $lineItems[] = [
                        'product_id' => $item['product_id'] ?? null,
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'discount_percent' => $item['discount_percent'] ?? 0,
                    ];
                }
                $this->repository->addLineItems($newContract['id'], $lineItems);
            }

            return $this->repository->findById($newContract['id']);
        });
    }

    // =========================================================================
    // LINE ITEM USE CASES
    // =========================================================================

    /**
     * Add line items to a contract.
     */
    public function addLineItems(int $contractId, array $items): array
    {
        return $this->repository->addLineItems($contractId, $items);
    }

    /**
     * Update a line item.
     */
    public function updateLineItem(int $lineItemId, array $data): array
    {
        return $this->repository->updateLineItem($lineItemId, $data);
    }

    /**
     * Delete a line item.
     */
    public function deleteLineItem(int $lineItemId): bool
    {
        return $this->repository->deleteLineItem($lineItemId);
    }

    /**
     * Reorder line items.
     */
    public function reorderLineItems(int $contractId, array $orderedIds): void
    {
        $this->repository->reorderLineItems($contractId, $orderedIds);
    }

    // =========================================================================
    // RENEWAL MANAGEMENT
    // =========================================================================

    /**
     * Set renewal reminder.
     */
    public function setRenewalReminder(int $contractId, int $daysBefore): array
    {
        $contract = $this->repository->findById($contractId);

        if (!$contract) {
            throw new \RuntimeException("Contract not found");
        }

        $endDate = new \DateTime($contract['end_date']);
        $renewalDate = (clone $endDate)->modify("-{$daysBefore} days")->format('Y-m-d');

        return $this->repository->update($contractId, [
            'renewal_notice_days' => $daysBefore,
            'renewal_date' => $renewalDate,
        ]);
    }

    /**
     * Mark renewal status.
     */
    public function markRenewalStatus(int $contractId, string $status): array
    {
        $validStatuses = ['pending', 'in_progress', 'completed', 'declined', 'expired'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid renewal status');
        }

        return $this->repository->update($contractId, ['renewal_status' => $status]);
    }

    /**
     * Get contracts needing renewal attention.
     */
    public function getContractsNeedingRenewalAttention(): array
    {
        return $this->repository->getContractsNeedingRenewalAttention();
    }
}
