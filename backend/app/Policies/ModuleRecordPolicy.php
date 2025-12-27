<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Entities\ModuleRecord;
use App\Infrastructure\Authorization\CachedAuthorizationService;
use App\Domain\User\Entities\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModuleRecordPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly CachedAuthorizationService $authService,
    ) {}

    /**
     * Determine if the user can view any records in the module.
     */
    public function viewAny(User $user, Module $module): bool
    {
        return $this->authService->canAccessModule($user->id, $module->id(), 'view');
    }

    /**
     * Determine if the user can view a specific record.
     */
    public function view(User $user, ModuleRecord $record): bool
    {
        $ownerId = $record->createdBy();

        return $this->authService->canViewRecord($user->id, $record->moduleId(), $ownerId);
    }

    /**
     * Determine if the user can create records in the module.
     */
    public function create(User $user, Module $module): bool
    {
        return $this->authService->canAccessModule($user->id, $module->id(), 'create');
    }

    /**
     * Determine if the user can update a specific record.
     */
    public function update(User $user, ModuleRecord $record): bool
    {
        $ownerId = $record->createdBy();

        return $this->authService->canEditRecord($user->id, $record->moduleId(), $ownerId);
    }

    /**
     * Determine if the user can delete a specific record.
     */
    public function delete(User $user, ModuleRecord $record): bool
    {
        $ownerId = $record->createdBy();

        return $this->authService->canDeleteRecord($user->id, $record->moduleId(), $ownerId);
    }

    /**
     * Determine if the user can export records from the module.
     */
    public function export(User $user, Module $module): bool
    {
        return $this->authService->canAccessModule($user->id, $module->id(), 'export');
    }

    /**
     * Determine if the user can import records into the module.
     */
    public function import(User $user, Module $module): bool
    {
        return $this->authService->canAccessModule($user->id, $module->id(), 'import');
    }
}
