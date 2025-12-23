<?php

declare(strict_types=1);

namespace App\Application\Services\Playbook;

use App\Domain\Playbook\Repositories\PlaybookRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class PlaybookApplicationService
{
    public function __construct(
        private readonly PlaybookRepositoryInterface $playbookRepository,
        private readonly AuthContextInterface $authContext
    ) {}

    // =========================================================================
    // QUERY USE CASES - PLAYBOOKS
    // =========================================================================

    /**
     * List playbooks with filtering and pagination.
     */
    public function listPlaybooks(array $filters = [], int $perPage = 25): PaginatedResult
    {
        return $this->playbookRepository->listPlaybooks($filters, $perPage);
    }

    /**
     * Get a single playbook by ID.
     */
    public function getPlaybook(int $id): ?array
    {
        return $this->playbookRepository->findById($id, [
            'phases.tasks',
            'tasks',
            'goals',
            'creator',
            'defaultOwner'
        ]);
    }

    /**
     * Get active playbooks for a module.
     */
    public function getActivePlaybooksForModule(string $module): array
    {
        return $this->playbookRepository->getActivePlaybooksForModule($module);
    }

    /**
     * Get playbook by slug.
     */
    public function getPlaybookBySlug(string $slug): ?array
    {
        return $this->playbookRepository->findBySlug($slug, ['phases.tasks', 'goals']);
    }

    // =========================================================================
    // QUERY USE CASES - PHASES & TASKS
    // =========================================================================

    /**
     * Get phases for a playbook.
     */
    public function getPhases(int $playbookId): array
    {
        return $this->playbookRepository->getPhases($playbookId);
    }

    /**
     * Get tasks for a playbook.
     */
    public function getTasks(int $playbookId): array
    {
        return $this->playbookRepository->getTasks($playbookId);
    }

    /**
     * Get a specific task.
     */
    public function getTask(int $taskId): ?array
    {
        return $this->playbookRepository->getTask($taskId);
    }

    // =========================================================================
    // QUERY USE CASES - INSTANCES
    // =========================================================================

    /**
     * List playbook instances with filtering and pagination.
     */
    public function listInstances(array $filters = [], int $perPage = 25): PaginatedResult
    {
        return $this->playbookRepository->listInstances($filters, $perPage);
    }

    /**
     * Get a single playbook instance.
     */
    public function getInstance(int $id): ?array
    {
        return $this->playbookRepository->getInstance($id);
    }

    /**
     * Get instances for a specific record.
     */
    public function getInstancesForRecord(string $module, int $recordId): array
    {
        return $this->playbookRepository->getInstancesForRecord($module, $recordId);
    }

    /**
     * Get active instances for a user.
     */
    public function getActiveInstancesForUser(int $userId): array
    {
        return $this->playbookRepository->getActiveInstancesForUser($userId);
    }

    // =========================================================================
    // QUERY USE CASES - TASK INSTANCES
    // =========================================================================

    /**
     * Get task instances for an instance.
     */
    public function getTaskInstances(int $instanceId): array
    {
        return $this->playbookRepository->getTaskInstances($instanceId);
    }

    /**
     * Get pending task instances for a user.
     */
    public function getPendingTasksForUser(int $userId): array
    {
        return $this->playbookRepository->getPendingTasksForUser($userId);
    }

    /**
     * Get overdue task instances for a user.
     */
    public function getOverdueTasksForUser(int $userId): array
    {
        return $this->playbookRepository->getOverdueTasksForUser($userId);
    }

    // =========================================================================
    // COMMAND USE CASES - PLAYBOOKS
    // =========================================================================

    /**
     * Create a new playbook.
     */
    public function createPlaybook(array $data): array
    {
        $data['created_by'] = $this->authContext->userId();

        $playbook = $this->playbookRepository->create($data);

        // Create phases if provided
        if (!empty($data['phases'])) {
            foreach ($data['phases'] as $index => $phaseData) {
                $this->createPhase($playbook['id'], array_merge($phaseData, [
                    'display_order' => $index,
                ]));
            }
        }

        return $this->playbookRepository->findById($playbook['id']);
    }

    /**
     * Update a playbook.
     */
    public function updatePlaybook(int $id, array $data): array
    {
        return $this->playbookRepository->update($id, $data);
    }

    /**
     * Delete a playbook.
     */
    public function deletePlaybook(int $id): bool
    {
        return $this->playbookRepository->delete($id);
    }

    /**
     * Duplicate a playbook.
     */
    public function duplicatePlaybook(int $id, string $newName): array
    {
        return $this->playbookRepository->duplicate(
            $id,
            $newName,
            $this->authContext->userId()
        );
    }

    // =========================================================================
    // COMMAND USE CASES - PHASES
    // =========================================================================

    /**
     * Create a phase for a playbook.
     */
    public function createPhase(int $playbookId, array $data): array
    {
        return $this->playbookRepository->createPhase($playbookId, $data);
    }

    /**
     * Update a phase.
     */
    public function updatePhase(int $id, array $data): array
    {
        return $this->playbookRepository->updatePhase($id, $data);
    }

    /**
     * Delete a phase.
     */
    public function deletePhase(int $id): bool
    {
        return $this->playbookRepository->deletePhase($id);
    }

    // =========================================================================
    // COMMAND USE CASES - TASKS
    // =========================================================================

    /**
     * Create a task for a playbook.
     */
    public function createTask(int $playbookId, array $data): array
    {
        return $this->playbookRepository->createTask($playbookId, $data);
    }

    /**
     * Update a task.
     */
    public function updateTask(int $id, array $data): array
    {
        return $this->playbookRepository->updateTask($id, $data);
    }

    /**
     * Delete a task.
     */
    public function deleteTask(int $id): bool
    {
        return $this->playbookRepository->deleteTask($id);
    }

    // =========================================================================
    // COMMAND USE CASES - INSTANCES
    // =========================================================================

    /**
     * Start a playbook instance for a record.
     */
    public function startInstance(int $playbookId, string $relatedModule, int $relatedId, ?int $ownerId = null): array
    {
        // Use provided owner ID, or fall back to default owner from playbook, or current user
        if ($ownerId === null) {
            $playbook = $this->playbookRepository->findById($playbookId);
            $ownerId = $playbook['default_owner_id'] ?? $this->authContext->userId();
        }

        return $this->playbookRepository->startInstance(
            $playbookId,
            $relatedModule,
            $relatedId,
            $ownerId
        );
    }

    /**
     * Update an instance.
     */
    public function updateInstance(int $id, array $data): array
    {
        return $this->playbookRepository->updateInstance($id, $data);
    }

    /**
     * Complete an instance.
     */
    public function completeInstance(int $id): array
    {
        return $this->playbookRepository->completeInstance($id);
    }

    /**
     * Pause an instance.
     */
    public function pauseInstance(int $id, ?string $reason = null): array
    {
        return $this->playbookRepository->pauseInstance($id, $reason);
    }

    /**
     * Resume an instance.
     */
    public function resumeInstance(int $id): array
    {
        return $this->playbookRepository->resumeInstance($id);
    }

    /**
     * Cancel an instance.
     */
    public function cancelInstance(int $id, ?string $reason = null): array
    {
        return $this->playbookRepository->cancelInstance($id, $reason);
    }

    // =========================================================================
    // COMMAND USE CASES - TASK INSTANCES
    // =========================================================================

    /**
     * Start a task instance.
     */
    public function startTaskInstance(int $taskInstanceId): array
    {
        return $this->playbookRepository->startTaskInstance($taskInstanceId);
    }

    /**
     * Complete a task instance.
     */
    public function completeTaskInstance(int $taskInstanceId, ?string $notes = null, ?int $timeSpent = null): array
    {
        return $this->playbookRepository->completeTaskInstance(
            $taskInstanceId,
            $this->authContext->userId(),
            $notes,
            $timeSpent
        );
    }

    /**
     * Skip a task instance.
     */
    public function skipTaskInstance(int $taskInstanceId, ?string $reason = null): array
    {
        return $this->playbookRepository->skipTaskInstance($taskInstanceId, $reason);
    }

    /**
     * Update a task instance checklist.
     */
    public function updateTaskChecklist(int $taskInstanceId, int $itemIndex, bool $completed): array
    {
        return $this->playbookRepository->updateTaskChecklist($taskInstanceId, $itemIndex, $completed);
    }

    // =========================================================================
    // COMMAND USE CASES - GOALS
    // =========================================================================

    /**
     * Create a goal for a playbook.
     */
    public function createGoal(int $playbookId, array $data): array
    {
        return $this->playbookRepository->createGoal($playbookId, $data);
    }

    /**
     * Record a goal result for an instance.
     */
    public function recordGoalResult(int $instanceId, int $goalId, $actualValue): array
    {
        return $this->playbookRepository->recordGoalResult($instanceId, $goalId, $actualValue);
    }

    // =========================================================================
    // ANALYTICS & REPORTING
    // =========================================================================

    /**
     * Get playbook analytics.
     */
    public function getPlaybookAnalytics(int $playbookId): array
    {
        return $this->playbookRepository->getPlaybookAnalytics($playbookId);
    }

    /**
     * Get instance progress summary.
     */
    public function getInstanceProgress(int $instanceId): array
    {
        return $this->playbookRepository->getInstanceProgress($instanceId);
    }

    /**
     * Get user task summary.
     */
    public function getUserTaskSummary(int $userId): array
    {
        return $this->playbookRepository->getUserTaskSummary($userId);
    }
}
