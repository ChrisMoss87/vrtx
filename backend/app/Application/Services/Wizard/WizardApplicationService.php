<?php

declare(strict_types=1);

namespace App\Application\Services\Wizard;

use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Wizard\Repositories\WizardRepositoryInterface;
use Illuminate\Support\Str;

class WizardApplicationService
{
    public function __construct(
        private WizardRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // WIZARD QUERY USE CASES
    // =========================================================================

    /**
     * List all wizards with optional filtering
     */
    public function getAllWizards(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }

    /**
     * List wizards with filtering and pagination
     */
    public function listWizards(array $filters = [], int $perPage = 15): PaginatedResult
    {
        return $this->repository->findWithFilters($filters, $perPage);
    }

    /**
     * Get a single wizard by ID
     */
    public function getWizard(int $wizardId): ?array
    {
        return $this->repository->findByIdWithRelations($wizardId);
    }

    /**
     * Get wizards for a specific module
     */
    public function getWizardsForModule(int $moduleId, bool $activeOnly = true): array
    {
        return $this->repository->findForModule($moduleId, $activeOnly);
    }

    /**
     * Get wizards by type
     */
    public function getWizardsByType(string $type, bool $activeOnly = true): array
    {
        return $this->repository->findByType($type, $activeOnly);
    }

    /**
     * Get default wizard for a module and type
     */
    public function getDefaultWizard(?int $moduleId, string $type): ?array
    {
        return $this->repository->findDefaultForModuleAndType($moduleId, $type);
    }

    /**
     * Check if wizard exists
     */
    public function wizardExists(int $wizardId): bool
    {
        return $this->repository->exists($wizardId);
    }

    // =========================================================================
    // WIZARD COMMAND USE CASES
    // =========================================================================

    /**
     * Create a new wizard
     */
    public function createWizard(array $data): array
    {
        // If setting as default, unset other defaults for this module/type
        if ($data['is_default'] ?? false) {
            $this->repository->unsetDefaultsExcept(
                $data['module_id'] ?? null,
                $data['type'],
                null
            );
        }

        $wizardData = [
            'name' => $data['name'],
            'api_name' => $data['api_name'] ?? Str::snake($data['name']),
            'description' => $data['description'] ?? null,
            'module_id' => $data['module_id'] ?? null,
            'type' => $data['type'],
            'is_active' => $data['is_active'] ?? true,
            'is_default' => $data['is_default'] ?? false,
            'settings' => $data['settings'] ?? [
                'showProgress' => true,
                'allowClickNavigation' => false,
                'saveAsDraft' => true,
            ],
            'created_by' => $this->authContext->userId(),
            'updated_by' => $this->authContext->userId(),
            'display_order' => $this->repository->getMaxDisplayOrder() + 1,
        ];

        $steps = $data['steps'] ?? [];

        return $this->repository->create($wizardData, $steps);
    }

    /**
     * Update a wizard
     */
    public function updateWizard(int $wizardId, array $data): array
    {
        $wizard = $this->repository->findById($wizardId);

        if (!$wizard) {
            throw new \RuntimeException('Wizard not found');
        }

        // If setting as default, unset other defaults
        $isDefault = $data['is_default'] ?? false;
        $wasDefault = $wizard['is_default'] ?? false;

        if ($isDefault && !$wasDefault) {
            $this->repository->unsetDefaultsExcept(
                $data['module_id'] ?? $wizard['module_id'],
                $data['type'] ?? $wizard['type'],
                $wizardId
            );
        }

        $updateData = [
            'name' => $data['name'] ?? null,
            'api_name' => $data['api_name'] ?? null,
            'description' => $data['description'] ?? null,
            'module_id' => $data['module_id'] ?? null,
            'type' => $data['type'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'is_default' => $data['is_default'] ?? null,
            'settings' => $data['settings'] ?? null,
            'updated_by' => $this->authContext->userId(),
        ];

        $steps = $data['steps'] ?? null;

        return $this->repository->update($wizardId, $updateData, $steps);
    }

    /**
     * Delete a wizard
     */
    public function deleteWizard(int $wizardId): bool
    {
        return $this->repository->delete($wizardId);
    }

    /**
     * Duplicate a wizard
     */
    public function duplicateWizard(int $wizardId, ?string $newName = null): array
    {
        return $this->repository->duplicate($wizardId, $newName, $this->authContext->userId());
    }

    /**
     * Activate a wizard
     */
    public function activateWizard(int $wizardId): array
    {
        return $this->repository->activate($wizardId);
    }

    /**
     * Deactivate a wizard
     */
    public function deactivateWizard(int $wizardId): array
    {
        return $this->repository->deactivate($wizardId);
    }

    /**
     * Toggle wizard active status
     */
    public function toggleActive(int $wizardId): array
    {
        return $this->repository->toggleActive($wizardId);
    }

    /**
     * Set wizard as default for its module/type
     */
    public function setAsDefault(int $wizardId): array
    {
        return $this->repository->setAsDefault($wizardId);
    }

    /**
     * Reorder wizards
     */
    public function reorderWizards(array $order): void
    {
        $this->repository->reorder($order);
    }

    // =========================================================================
    // WIZARD STEP QUERY USE CASES
    // =========================================================================

    /**
     * Get steps for a wizard
     */
    public function getSteps(int $wizardId): array
    {
        return $this->repository->findSteps($wizardId);
    }

    /**
     * Get a single step
     */
    public function getStep(int $stepId): ?array
    {
        return $this->repository->findStepById($stepId);
    }

    // =========================================================================
    // WIZARD STEP COMMAND USE CASES
    // =========================================================================

    /**
     * Create a step for a wizard
     */
    public function createStep(int $wizardId, array $data): array
    {
        return $this->repository->createStep($wizardId, $data);
    }

    /**
     * Update a step
     */
    public function updateStep(int $stepId, array $data): array
    {
        return $this->repository->updateStep($stepId, $data);
    }

    /**
     * Delete a step
     */
    public function deleteStep(int $stepId): bool
    {
        return $this->repository->deleteStep($stepId);
    }

    /**
     * Reorder steps for a wizard
     */
    public function reorderSteps(int $wizardId, array $order): void
    {
        $this->repository->reorderSteps($wizardId, $order);
    }

    // =========================================================================
    // FORMATTING USE CASES
    // =========================================================================

    /**
     * Format wizard for API response
     */
    public function formatWizard(array $wizard): array
    {
        return [
            'id' => $wizard['id'],
            'name' => $wizard['name'],
            'api_name' => $wizard['api_name'],
            'description' => $wizard['description'],
            'type' => $wizard['type'],
            'is_active' => $wizard['is_active'],
            'is_default' => $wizard['is_default'],
            'settings' => $wizard['settings'] ?? [
                'showProgress' => true,
                'allowClickNavigation' => false,
                'saveAsDraft' => true,
            ],
            'display_order' => $wizard['display_order'],
            'module' => isset($wizard['module']) && $wizard['module'] ? [
                'id' => $wizard['module']['id'],
                'name' => $wizard['module']['name'],
                'api_name' => $wizard['module']['api_name'],
            ] : null,
            'creator' => isset($wizard['creator']) && $wizard['creator'] ? [
                'id' => $wizard['creator']['id'],
                'name' => $wizard['creator']['name'],
            ] : null,
            'steps' => array_map(fn ($step) => [
                'id' => $step['id'],
                'title' => $step['title'],
                'description' => $step['description'],
                'type' => $step['type'],
                'fields' => $step['fields'] ?? [],
                'can_skip' => $step['can_skip'],
                'display_order' => $step['display_order'],
                'conditional_logic' => $step['conditional_logic'],
                'validation_rules' => $step['validation_rules'],
            ], $wizard['steps'] ?? []),
            'step_count' => count($wizard['steps'] ?? []),
            'field_count' => array_sum(array_map(fn ($step) => count($step['fields'] ?? []), $wizard['steps'] ?? [])),
            'created_at' => $wizard['created_at'] ?? null,
            'updated_at' => $wizard['updated_at'] ?? null,
        ];
    }

    /**
     * Format step for API response
     */
    public function formatStep(array $step): array
    {
        return [
            'id' => $step['id'],
            'wizard_id' => $step['wizard_id'],
            'title' => $step['title'],
            'description' => $step['description'],
            'type' => $step['type'],
            'fields' => $step['fields'] ?? [],
            'can_skip' => $step['can_skip'],
            'display_order' => $step['display_order'],
            'conditional_logic' => $step['conditional_logic'],
            'validation_rules' => $step['validation_rules'],
            'created_at' => $step['created_at'] ?? null,
            'updated_at' => $step['updated_at'] ?? null,
        ];
    }
}
