<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Repositories;

use App\Domain\Workflow\Entities\Workflow;
use App\Domain\Workflow\ValueObjects\TriggerType;

/**
 * Repository interface for Workflow aggregate root.
 */
interface WorkflowRepositoryInterface
{
    /**
     * Find a workflow by its ID.
     */
    public function findById(int $id): ?Workflow;

    /**
     * Find all workflows.
     *
     * @return array<Workflow>
     */
    public function findAll(): array;

    /**
     * Find active workflows for a module.
     *
     * @return array<Workflow>
     */
    public function findActiveForModule(int $moduleId): array;

    /**
     * Find workflows by trigger type for a module.
     *
     * @return array<Workflow>
     */
    public function findByTriggerType(int $moduleId, TriggerType $triggerType): array;

    /**
     * Find workflows that should run at a scheduled time.
     *
     * @return array<Workflow>
     */
    public function findScheduledForExecution(): array;

    /**
     * Save a workflow (insert or update).
     */
    public function save(Workflow $workflow): Workflow;

    /**
     * Delete a workflow.
     */
    public function delete(int $id): bool;

    /**
     * Check if a workflow has run for a specific record.
     */
    public function hasRunForRecord(int $workflowId, int $recordId, string $recordType, ?string $triggerType = null): bool;

    /**
     * Record that a workflow has run for a specific record.
     */
    public function recordRunForRecord(int $workflowId, int $recordId, string $recordType, string $triggerType): void;
}
