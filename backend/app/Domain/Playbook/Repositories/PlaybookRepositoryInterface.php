<?php

declare(strict_types=1);

namespace App\Domain\Playbook\Repositories;

use App\Domain\Playbook\Entities\Playbook;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface PlaybookRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?Playbook;

    public function save(Playbook $entity): Playbook;

    public function delete(int $id): bool;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id, array $relations = []): ?array;

    // =========================================================================
    // PLAYBOOK QUERIES
    // =========================================================================

    /**
     * Find a playbook by slug with relations.
     *
     * @param string $slug
     * @param array $relations Relations to load
     * @return array|null
     */
    public function findBySlug(string $slug, array $relations = []): ?array;

    /**
     * List playbooks with filtering and pagination.
     *
     * @param array $filters Available filters:
     *   - is_active: bool
     *   - trigger_module: string
     *   - created_by: int
     *   - search: string
     *   - sort_by: string (default: 'display_order')
     *   - sort_dir: string (default: 'asc')
     * @param int $perPage
     * @param int $page
     * @return PaginatedResult
     */
    public function listPlaybooks(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get active playbooks for a module.
     *
     * @param string $module
     * @return array
     */
    public function getActivePlaybooksForModule(string $module): array;

    /**
     * Create a new playbook.
     *
     * @param array $data
     * @return array
     */
    public function create(array $data): array;

    /**
     * Update a playbook.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function update(int $id, array $data): array;

    /**
     * Duplicate a playbook.
     *
     * @param int $id
     * @param string $newName
     * @param int $createdBy
     * @return array
     */
    public function duplicate(int $id, string $newName, int $createdBy): array;

    // =========================================================================
    // PHASE QUERIES
    // =========================================================================

    /**
     * Get phases for a playbook.
     *
     * @param int $playbookId
     * @return array
     */
    public function getPhases(int $playbookId): array;

    /**
     * Create a phase.
     *
     * @param int $playbookId
     * @param array $data
     * @return array
     */
    public function createPhase(int $playbookId, array $data): array;

    /**
     * Update a phase.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updatePhase(int $id, array $data): array;

    /**
     * Delete a phase.
     *
     * @param int $id
     * @return bool
     */
    public function deletePhase(int $id): bool;

    // =========================================================================
    // TASK QUERIES
    // =========================================================================

    /**
     * Get tasks for a playbook.
     *
     * @param int $playbookId
     * @return array
     */
    public function getTasks(int $playbookId): array;

    /**
     * Get a specific task.
     *
     * @param int $taskId
     * @return array|null
     */
    public function getTask(int $taskId): ?array;

    /**
     * Create a task.
     *
     * @param int $playbookId
     * @param array $data
     * @return array
     */
    public function createTask(int $playbookId, array $data): array;

    /**
     * Update a task.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateTask(int $id, array $data): array;

    /**
     * Delete a task.
     *
     * @param int $id
     * @return bool
     */
    public function deleteTask(int $id): bool;

    // =========================================================================
    // INSTANCE QUERIES
    // =========================================================================

    /**
     * List playbook instances with filtering and pagination.
     *
     * @param array $filters Available filters:
     *   - playbook_id: int
     *   - status: string
     *   - owner_id: int
     *   - related_module: string
     *   - related_id: int
     *   - started_from: string (date)
     *   - started_to: string (date)
     *   - sort_by: string (default: 'created_at')
     *   - sort_dir: string (default: 'desc')
     * @param int $perPage
     * @param int $page
     * @return PaginatedResult
     */
    public function listInstances(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get a single playbook instance.
     *
     * @param int $id
     * @return array|null
     */
    public function getInstance(int $id): ?array;

    /**
     * Get instances for a specific record.
     *
     * @param string $module
     * @param int $recordId
     * @return array
     */
    public function getInstancesForRecord(string $module, int $recordId): array;

    /**
     * Get active instances for a user.
     *
     * @param int $userId
     * @return array
     */
    public function getActiveInstancesForUser(int $userId): array;

    /**
     * Start a playbook instance.
     *
     * @param int $playbookId
     * @param string $relatedModule
     * @param int $relatedId
     * @param int $ownerId
     * @return array
     */
    public function startInstance(int $playbookId, string $relatedModule, int $relatedId, int $ownerId): array;

    /**
     * Update an instance.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateInstance(int $id, array $data): array;

    /**
     * Complete an instance.
     *
     * @param int $id
     * @return array
     */
    public function completeInstance(int $id): array;

    /**
     * Pause an instance.
     *
     * @param int $id
     * @param string|null $reason
     * @return array
     */
    public function pauseInstance(int $id, ?string $reason = null): array;

    /**
     * Resume an instance.
     *
     * @param int $id
     * @return array
     */
    public function resumeInstance(int $id): array;

    /**
     * Cancel an instance.
     *
     * @param int $id
     * @param string|null $reason
     * @return array
     */
    public function cancelInstance(int $id, ?string $reason = null): array;

    // =========================================================================
    // TASK INSTANCE QUERIES
    // =========================================================================

    /**
     * Get task instances for an instance.
     *
     * @param int $instanceId
     * @return array
     */
    public function getTaskInstances(int $instanceId): array;

    /**
     * Get pending task instances for a user.
     *
     * @param int $userId
     * @return array
     */
    public function getPendingTasksForUser(int $userId): array;

    /**
     * Get overdue task instances for a user.
     *
     * @param int $userId
     * @return array
     */
    public function getOverdueTasksForUser(int $userId): array;

    /**
     * Start a task instance.
     *
     * @param int $taskInstanceId
     * @return array
     */
    public function startTaskInstance(int $taskInstanceId): array;

    /**
     * Complete a task instance.
     *
     * @param int $taskInstanceId
     * @param int $completedBy
     * @param string|null $notes
     * @param int|null $timeSpent
     * @return array
     */
    public function completeTaskInstance(int $taskInstanceId, int $completedBy, ?string $notes = null, ?int $timeSpent = null): array;

    /**
     * Skip a task instance.
     *
     * @param int $taskInstanceId
     * @param string|null $reason
     * @return array
     */
    public function skipTaskInstance(int $taskInstanceId, ?string $reason = null): array;

    /**
     * Update task checklist.
     *
     * @param int $taskInstanceId
     * @param int $itemIndex
     * @param bool $completed
     * @return array
     */
    public function updateTaskChecklist(int $taskInstanceId, int $itemIndex, bool $completed): array;

    // =========================================================================
    // GOAL QUERIES
    // =========================================================================

    /**
     * Create a goal.
     *
     * @param int $playbookId
     * @param array $data
     * @return array
     */
    public function createGoal(int $playbookId, array $data): array;

    /**
     * Record a goal result.
     *
     * @param int $instanceId
     * @param int $goalId
     * @param mixed $actualValue
     * @return array
     */
    public function recordGoalResult(int $instanceId, int $goalId, $actualValue): array;

    // =========================================================================
    // ANALYTICS QUERIES
    // =========================================================================

    /**
     * Get playbook analytics.
     *
     * @param int $playbookId
     * @return array
     */
    public function getPlaybookAnalytics(int $playbookId): array;

    /**
     * Get instance progress.
     *
     * @param int $instanceId
     * @return array
     */
    public function getInstanceProgress(int $instanceId): array;

    /**
     * Get user task summary.
     *
     * @param int $userId
     * @return array
     */
    public function getUserTaskSummary(int $userId): array;
}
