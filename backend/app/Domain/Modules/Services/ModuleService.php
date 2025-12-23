<?php

declare(strict_types=1);

namespace App\Domain\Modules\Services;

use App\Domain\Modules\DTOs\CreateModuleDTO;
use App\Domain\Modules\DTOs\UpdateModuleDTO;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleSettings;
use Illuminate\Support\Str;

class ModuleService
{
    public function __construct(
        private readonly ModuleRepositoryInterface $moduleRepository
    ) {}

    /**
     * Get all modules.
     */
    public function getAllModules(): array
    {
        return $this->moduleRepository->findAll();
    }

    /**
     * Get only active modules.
     */
    public function getActiveModules(): array
    {
        return $this->moduleRepository->findActive();
    }

    /**
     * Get a module by ID.
     */
    public function getModuleById(int $id): ?Module
    {
        return $this->moduleRepository->findById($id);
    }

    /**
     * Get a module by API name.
     */
    public function getModuleByApiName(string $apiName): ?Module
    {
        return $this->moduleRepository->findByApiName($apiName);
    }

    /**
     * Create a new module.
     */
    public function createModule(CreateModuleDTO $dto): Module
    {
        // Validate unique name
        if ($this->moduleRepository->existsByName($dto->name)) {
            throw new \DomainException("Module with name '{$dto->name}' already exists.");
        }

        // Create entity
        $module = Module::create(
            name: $dto->name,
            singularName: $dto->singularName,
            icon: $dto->icon,
            description: $dto->description,
            settings: ModuleSettings::fromArray($dto->settings),
            displayOrder: $dto->displayOrder
        );

        // Save to repository
        return $this->moduleRepository->save($module);
    }

    /**
     * Update an existing module.
     */
    public function updateModule(UpdateModuleDTO $dto): Module
    {
        $module = $this->moduleRepository->findById($dto->id);

        if (!$module) {
            throw new \DomainException("Module not found.");
        }

        // Update name if provided
        if ($dto->name !== null && $dto->singularName !== null) {
            // Check for unique name
            if ($this->moduleRepository->existsByName($dto->name, $dto->id)) {
                throw new \DomainException("Module with name '{$dto->name}' already exists.");
            }

            $module = $module->updateDetails(
                name: $dto->name,
                singularName: $dto->singularName,
                icon: $dto->icon ?? $module->getIcon(),
                description: $dto->description ?? $module->getDescription()
            );
        }

        // Update settings if provided
        if ($dto->settings !== null) {
            $module = $module->updateSettings(ModuleSettings::fromArray($dto->settings));
        }

        // Update display order if provided
        if ($dto->displayOrder !== null) {
            $module = $module->updateDisplayOrder($dto->displayOrder);
        }

        // Update active status if provided
        if ($dto->isActive !== null) {
            $module = $dto->isActive ? $module->activate() : $module->deactivate();
        }

        return $this->moduleRepository->save($module);
    }

    /**
     * Delete a module.
     */
    public function deleteModule(int $id): bool
    {
        return $this->moduleRepository->delete($id);
    }

    /**
     * Activate a module.
     */
    public function activateModule(int $id): Module
    {
        $module = $this->moduleRepository->findById($id);

        if (!$module) {
            throw new \DomainException("Module not found.");
        }

        $module = $module->activate();

        return $this->moduleRepository->save($module);
    }

    /**
     * Deactivate a module.
     */
    public function deactivateModule(int $id): Module
    {
        $module = $this->moduleRepository->findById($id);

        if (!$module) {
            throw new \DomainException("Module not found.");
        }

        $module = $module->deactivate();

        return $this->moduleRepository->save($module);
    }
}
