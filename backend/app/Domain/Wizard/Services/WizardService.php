<?php

declare(strict_types=1);

namespace App\Domain\Wizard\Services;

use App\Domain\Wizard\Repositories\WizardRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WizardService
{
    public function __construct(
        private WizardRepositoryInterface $wizardRepository,
    ) {}

    /**
     * List all wizards with optional filtering.
     */
    public function listWizards(
        ?int $moduleId = null,
        ?string $type = null,
        bool $activeOnly = false
    ): Collection {
        return $this->wizardRepository->list($moduleId, $type, $activeOnly);
    }

    /**
     * Get a wizard by ID.
     */
    public function getWizard(int $id): ?array
    {
        return $this->wizardRepository->findByIdWithSteps($id);
    }

    /**
     * Create a new wizard.
     */
    public function createWizard(array $data, array $steps, int $createdBy): array
    {
        // If setting as default, unset other defaults for this module/type
        if ($data['is_default'] ?? false) {
            $this->wizardRepository->unsetDefaultsExcept(
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
            'created_by' => $createdBy,
            'display_order' => $this->wizardRepository->getMaxDisplayOrder() + 1,
        ];

        return $this->wizardRepository->create($wizardData, $steps);
    }

    /**
     * Update a wizard.
     */
    public function updateWizard(int $id, array $data, ?array $steps = null): array
    {
        $wizard = $this->wizardRepository->findById($id);

        if (!$wizard) {
            throw new \RuntimeException('Wizard not found');
        }

        // If setting as default, unset other defaults
        $isDefault = $data['is_default'] ?? false;
        $wasDefault = $wizard['is_default'] ?? false;

        if ($isDefault && !$wasDefault) {
            $this->wizardRepository->unsetDefaultsExcept(
                $data['module_id'] ?? $wizard['module_id'],
                $data['type'] ?? $wizard['type'],
                $id
            );
        }

        $updateData = collect($data)->except('steps')->filter(fn ($v) => $v !== null)->toArray();

        return $this->wizardRepository->update($id, $updateData, $steps);
    }

    /**
     * Delete a wizard.
     */
    public function deleteWizard(int $id): bool
    {
        return $this->wizardRepository->delete($id);
    }

    /**
     * Duplicate a wizard.
     */
    public function duplicateWizard(int $id): array
    {
        return $this->wizardRepository->duplicate($id);
    }

    /**
     * Reorder wizards.
     */
    public function reorderWizards(array $order): void
    {
        $this->wizardRepository->reorder($order);
    }

    /**
     * Toggle wizard active status.
     */
    public function toggleActive(int $id): array
    {
        return $this->wizardRepository->toggleActive($id);
    }

    /**
     * Get wizards for a module.
     */
    public function getWizardsForModule(int $moduleId): Collection
    {
        return $this->wizardRepository->getForModule($moduleId, true);
    }

    /**
     * Check if wizard exists.
     */
    public function wizardExists(int $id): bool
    {
        return $this->wizardRepository->findById($id) !== null;
    }

    /**
     * Format wizard for API response.
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
            'steps' => collect($wizard['steps'] ?? [])->map(fn ($step) => [
                'id' => $step['id'],
                'title' => $step['title'],
                'description' => $step['description'],
                'type' => $step['type'],
                'fields' => $step['fields'] ?? [],
                'can_skip' => $step['can_skip'],
                'display_order' => $step['display_order'],
                'conditional_logic' => $step['conditional_logic'],
                'validation_rules' => $step['validation_rules'],
            ])->toArray(),
            'step_count' => count($wizard['steps'] ?? []),
            'field_count' => collect($wizard['steps'] ?? [])->sum(fn ($step) => count($step['fields'] ?? [])),
            'created_at' => $wizard['created_at'] ?? null,
            'updated_at' => $wizard['updated_at'] ?? null,
        ];
    }
}
