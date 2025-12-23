<?php

declare(strict_types=1);

namespace App\Application\Services\Cadence;

use App\Domain\Cadence\Repositories\CadenceRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;

/**
 * Application Service for Cadence (Sales Sequence) operations.
 *
 * Cadences are automated multi-step outreach sequences that help sales teams
 * systematically engage with prospects through various channels (email, calls, etc.)
 */
class CadenceApplicationService
{
    public function __construct(
        private CadenceRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - CADENCES
    // =========================================================================

    /**
     * List cadences with filters and pagination.
     */
    public function listCadences(array $filters = []): PaginatedResult
    {
        $perPage = $filters['per_page'] ?? 20;
        return $this->repository->findWithFilters($filters, $perPage);
    }

    /**
     * Get a single cadence with all related data.
     */
    public function getCadence(int $id): ?array
    {
        return $this->repository->findByIdWithRelations($id);
    }

    // =========================================================================
    // COMMAND USE CASES - CADENCES
    // =========================================================================

    /**
     * Create a new cadence.
     */
    public function createCadence(array $data): array
    {
        $cadence = $this->repository->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'module_id' => $data['module_id'],
            'status' => 'draft',
            'entry_criteria' => $data['entry_criteria'] ?? null,
            'exit_criteria' => $data['exit_criteria'] ?? null,
            'settings' => $data['settings'] ?? [],
            'auto_enroll' => $data['auto_enroll'] ?? false,
            'allow_re_enrollment' => $data['allow_re_enrollment'] ?? false,
            're_enrollment_days' => $data['re_enrollment_days'] ?? null,
            'max_enrollments_per_day' => $data['max_enrollments_per_day'] ?? null,
            'owner_id' => $data['owner_id'] ?? $this->authContext->userId(),
            'created_by' => $this->authContext->userId(),
        ]);

        if (!empty($data['steps'])) {
            foreach ($data['steps'] as $index => $stepData) {
                $this->createStep($cadence['id'], array_merge($stepData, ['step_order' => $index + 1]));
            }
        }

        return $this->repository->findByIdWithRelations($cadence['id']);
    }

    /**
     * Update a cadence.
     */
    public function updateCadence(int $id, array $data): array
    {
        return $this->repository->update($id, $data);
    }

    /**
     * Delete a cadence.
     */
    public function deleteCadence(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Activate a cadence.
     */
    public function activateCadence(int $id): array
    {
        $steps = $this->repository->findStepsForCadence($id, activeOnly: true);

        if (empty($steps)) {
            throw new \InvalidArgumentException('Cannot activate cadence without active steps');
        }

        return $this->repository->update($id, ['status' => 'active']);
    }

    /**
     * Pause a cadence.
     */
    public function pauseCadence(int $id): array
    {
        return $this->repository->update($id, ['status' => 'paused']);
    }

    /**
     * Archive a cadence.
     */
    public function archiveCadence(int $id): array
    {
        return $this->repository->update($id, ['status' => 'archived']);
    }

    /**
     * Duplicate a cadence.
     */
    public function duplicateCadence(int $id): array
    {
        $original = $this->repository->findByIdWithRelations($id);

        if (!$original) {
            throw new \InvalidArgumentException('Cadence not found');
        }

        $newName = $original['name'] . ' (Copy)';
        return $this->repository->duplicate($id, $newName, $this->authContext->userId());
    }

    // =========================================================================
    // COMMAND USE CASES - STEPS
    // =========================================================================

    /**
     * Create a cadence step.
     */
    public function createStep(int $cadenceId, array $data): array
    {
        if (!isset($data['name']) && isset($data['step_order'])) {
            $data['name'] = "Step {$data['step_order']}";
        }

        $stepData = [
            'name' => $data['name'] ?? null,
            'channel' => $data['channel'],
            'delay_type' => $data['delay_type'],
            'delay_value' => $data['delay_value'],
            'step_order' => $data['step_order'] ?? null,
            'preferred_time' => $data['preferred_time'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'conditions' => $data['conditions'] ?? null,
            'on_reply_goto_step' => $data['on_reply_goto_step'] ?? null,
            'on_click_goto_step' => $data['on_click_goto_step'] ?? null,
            'on_no_response_goto_step' => $data['on_no_response_goto_step'] ?? null,
            'is_ab_test' => $data['is_ab_test'] ?? false,
            'ab_variant_of' => $data['ab_variant_of'] ?? null,
            'ab_percentage' => $data['ab_percentage'] ?? null,
            'linkedin_action' => $data['linkedin_action'] ?? null,
            'task_type' => $data['task_type'] ?? null,
            'task_assigned_to' => $data['task_assigned_to'] ?? null,
            'is_active' => true,
        ];

        return $this->repository->createStep($cadenceId, $stepData);
    }

    /**
     * Update a cadence step.
     */
    public function updateStep(int $stepId, array $data): array
    {
        return $this->repository->updateStep($stepId, $data);
    }

    /**
     * Delete a cadence step.
     */
    public function deleteStep(int $stepId): bool
    {
        return $this->repository->deleteStep($stepId);
    }

    /**
     * Reorder cadence steps.
     */
    public function reorderSteps(int $cadenceId, array $stepIds): void
    {
        $this->repository->reorderSteps($cadenceId, $stepIds);
    }

    // =========================================================================
    // COMMAND USE CASES - ENROLLMENTS
    // =========================================================================

    /**
     * Enroll a record in a cadence.
     */
    public function enrollRecord(int $cadenceId, int $recordId): array
    {
        if (!$this->repository->canEnroll($cadenceId)) {
            throw new \InvalidArgumentException('Cadence is not accepting enrollments');
        }

        // Check for existing enrollment
        $existing = $this->repository->findActiveEnrollmentForRecord($cadenceId, $recordId);

        if ($existing) {
            throw new \InvalidArgumentException('Record is already enrolled in this cadence');
        }

        // Check re-enrollment rules
        $cadence = $this->repository->findById($cadenceId);
        if ($cadence && !($cadence['allow_re_enrollment'] ?? false)) {
            $previousEnrollment = $this->repository->findPreviousEnrollment($cadenceId, $recordId);

            if ($previousEnrollment) {
                throw new \InvalidArgumentException('Re-enrollment is not allowed for this cadence');
            }
        }

        $steps = $this->repository->findStepsForCadence($cadenceId, activeOnly: true);
        $firstStep = !empty($steps) ? $steps[0] : null;

        $nextStepAt = $firstStep ? $this->repository->calculateNextStepTime($firstStep['id']) : null;

        return $this->repository->createEnrollment([
            'cadence_id' => $cadenceId,
            'record_id' => $recordId,
            'status' => 'active',
            'current_step_id' => $firstStep['id'] ?? null,
            'enrolled_at' => now(),
            'enrolled_by' => $this->authContext->userId(),
            'next_step_at' => $nextStepAt,
        ]);
    }

    /**
     * Bulk enroll records.
     */
    public function bulkEnroll(int $cadenceId, array $recordIds): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($recordIds as $recordId) {
            try {
                $this->enrollRecord($cadenceId, $recordId);
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $errors[$recordId] = $e->getMessage();
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Unenroll from a cadence.
     */
    public function unenroll(int $enrollmentId, string $reason = 'Manually removed'): array
    {
        return $this->repository->updateEnrollment($enrollmentId, [
            'status' => 'completed',
            'completed_at' => now(),
            'exit_reason' => $reason,
        ]);
    }

    /**
     * Pause an enrollment.
     */
    public function pauseEnrollment(int $enrollmentId): array
    {
        return $this->repository->updateEnrollment($enrollmentId, [
            'status' => 'paused',
            'paused_at' => now(),
        ]);
    }

    /**
     * Resume a paused enrollment.
     */
    public function resumeEnrollment(int $enrollmentId): array
    {
        $enrollment = $this->repository->findEnrollmentById($enrollmentId);

        if (!$enrollment) {
            throw new \InvalidArgumentException('Enrollment not found');
        }

        if ($enrollment['status'] !== 'paused') {
            throw new \InvalidArgumentException('Enrollment is not paused');
        }

        $nextStepAt = $enrollment['current_step_id']
            ? $this->repository->calculateNextStepTime($enrollment['current_step_id'])
            : null;

        return $this->repository->updateEnrollment($enrollmentId, [
            'status' => 'active',
            'paused_at' => null,
            'next_step_at' => $nextStepAt,
        ]);
    }

    /**
     * Get enrollments for a cadence.
     */
    public function getEnrollments(int $cadenceId, array $filters = []): PaginatedResult
    {
        $perPage = $filters['per_page'] ?? 20;
        return $this->repository->findEnrollments($cadenceId, $filters, $perPage);
    }

    // =========================================================================
    // QUERY USE CASES - ANALYTICS
    // =========================================================================

    /**
     * Get analytics for a cadence.
     */
    public function getAnalytics(int $cadenceId, ?string $startDate = null, ?string $endDate = null): array
    {
        $summary = $this->repository->getAnalytics($cadenceId, $startDate, $endDate);
        $stepPerformance = $this->repository->getStepPerformance($cadenceId);

        return [
            'summary' => $summary,
            'step_performance' => $stepPerformance,
        ];
    }

    // =========================================================================
    // QUERY USE CASES - TEMPLATES
    // =========================================================================

    /**
     * Get cadence templates.
     */
    public function getTemplates(?string $category = null): array
    {
        return $this->repository->findTemplates($category);
    }

    // =========================================================================
    // COMMAND USE CASES - TEMPLATES
    // =========================================================================

    /**
     * Create a cadence from a template.
     */
    public function createFromTemplate(int $templateId, int $moduleId, string $name): array
    {
        $template = $this->repository->findTemplateById($templateId);

        if (!$template) {
            throw new \InvalidArgumentException('Template not found');
        }

        $cadence = $this->repository->create([
            'name' => $name,
            'description' => $template['description'] ?? null,
            'module_id' => $moduleId,
            'status' => 'draft',
            'settings' => $template['settings'] ?? [],
            'owner_id' => $this->authContext->userId(),
            'created_by' => $this->authContext->userId(),
        ]);

        // Copy template steps from steps_config
        if (!empty($template['steps_config'])) {
            foreach ($template['steps_config'] as $index => $stepConfig) {
                $this->repository->createStep($cadence['id'], [
                    'name' => $stepConfig['name'] ?? "Step " . ($index + 1),
                    'channel' => $stepConfig['channel'] ?? 'email',
                    'delay_type' => $stepConfig['delay_type'] ?? 'days',
                    'delay_value' => $stepConfig['delay_value'] ?? 1,
                    'step_order' => $stepConfig['step_order'] ?? ($index + 1),
                    'subject' => $stepConfig['subject'] ?? null,
                    'content' => $stepConfig['content'] ?? null,
                    'conditions' => $stepConfig['conditions'] ?? null,
                    'is_active' => true,
                ]);
            }
        }

        return $this->repository->findByIdWithRelations($cadence['id']);
    }

    /**
     * Save a cadence as a template.
     */
    public function saveAsTemplate(int $cadenceId, string $name, ?string $category = null): array
    {
        $cadence = $this->repository->findByIdWithRelations($cadenceId);

        if (!$cadence) {
            throw new \InvalidArgumentException('Cadence not found');
        }

        // Convert steps to steps_config array
        $stepsConfig = [];
        if (!empty($cadence['steps'])) {
            foreach ($cadence['steps'] as $step) {
                $stepsConfig[] = [
                    'name' => $step['name'],
                    'channel' => $step['channel'],
                    'delay_type' => $step['delay_type'],
                    'delay_value' => $step['delay_value'],
                    'step_order' => $step['step_order'],
                    'subject' => $step['subject'] ?? null,
                    'content' => $step['content'] ?? null,
                    'conditions' => $step['conditions'] ?? null,
                ];
            }
        }

        return $this->repository->createTemplate([
            'name' => $name,
            'description' => $cadence['description'] ?? null,
            'category' => $category,
            'settings' => $cadence['settings'] ?? [],
            'steps_config' => $stepsConfig,
            'created_by' => $this->authContext->userId(),
        ]);
    }
}
