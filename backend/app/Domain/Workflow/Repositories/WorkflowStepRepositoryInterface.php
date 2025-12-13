<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Repositories;

use App\Domain\Workflow\Entities\WorkflowStep;

/**
 * Repository interface for WorkflowStep entities.
 */
interface WorkflowStepRepositoryInterface
{
    /**
     * Find a step by its ID.
     */
    public function findById(int $id): ?WorkflowStep;

    /**
     * Find all steps for a workflow, ordered by step order.
     *
     * @return array<WorkflowStep>
     */
    public function findByWorkflowId(int $workflowId): array;

    /**
     * Save a step (insert or update).
     */
    public function save(WorkflowStep $step): WorkflowStep;

    /**
     * Delete a step.
     */
    public function delete(int $id): bool;

    /**
     * Delete all steps for a workflow.
     */
    public function deleteByWorkflowId(int $workflowId): int;

    /**
     * Bulk save steps for a workflow.
     *
     * @param array<WorkflowStep> $steps
     * @return array<WorkflowStep>
     */
    public function saveMany(int $workflowId, array $steps): array;
}
