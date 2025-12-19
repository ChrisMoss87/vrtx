<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Repositories;

use App\Domain\Workflow\Entities\WorkflowExecution;
use App\Domain\Workflow\Entities\WorkflowStepLog;
use App\Domain\Workflow\ValueObjects\ExecutionStatus;
use DateTimeImmutable;

/**
 * Repository interface for WorkflowExecution aggregate root.
 */
interface WorkflowExecutionRepositoryInterface
{
    /**
     * Find an execution by its ID.
     */
    public function findById(int $id): ?WorkflowExecution;

    /**
     * Find executions for a workflow.
     *
     * @return array<WorkflowExecution>
     */
    public function findByWorkflowId(int $workflowId, int $limit = 50): array;

    /**
     * Find executions by status.
     *
     * @return array<WorkflowExecution>
     */
    public function findByStatus(ExecutionStatus $status, int $limit = 100): array;

    /**
     * Find recent executions.
     *
     * @return array<WorkflowExecution>
     */
    public function findRecent(int $days = 7, int $limit = 100): array;

    /**
     * Find executions for a specific record.
     *
     * @return array<WorkflowExecution>
     */
    public function findByRecord(int $recordId, string $recordType, int $limit = 50): array;

    /**
     * Save an execution (insert or update).
     */
    public function save(WorkflowExecution $execution): WorkflowExecution;

    /**
     * Delete an execution.
     */
    public function delete(int $id): bool;

    /**
     * Save a step log entry.
     */
    public function saveStepLog(WorkflowStepLog $log): WorkflowStepLog;

    /**
     * Find step logs for an execution.
     *
     * @return array<WorkflowStepLog>
     */
    public function findStepLogs(int $executionId): array;

    /**
     * Get execution statistics for a workflow.
     *
     * @return array{total: int, completed: int, failed: int, cancelled: int, avg_duration_ms: float}
     */
    public function getStatisticsForWorkflow(int $workflowId, ?DateTimeImmutable $since = null): array;

    /**
     * Clean up old executions.
     *
     * @return int Number of deleted executions
     */
    public function cleanupOlderThan(DateTimeImmutable $cutoff): int;
}
