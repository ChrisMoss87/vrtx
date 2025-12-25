<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Entities\ModuleRecord;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use App\Services\RbacService;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModuleRecordPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private RbacService $rbacService
    ) {}

    /**
     * Determine if the user can view any records in the module.
     */
    public function viewAny(User $user, Module $module): bool
    {
        return $this->rbacService->canAccessModule($user, $module, 'view');
    }

    /**
     * Determine if the user can view a specific record.
     */
    public function view(User $user, ModuleRecord $record): bool
    {
        return $this->rbacService->canViewRecord($user, $record);
    }

    /**
     * Determine if the user can create records in the module.
     */
    public function create(User $user, Module $module): bool
    {
        return $this->rbacService->canAccessModule($user, $module, 'create');
    }

    /**
     * Determine if the user can update a specific record.
     */
    public function update(User $user, ModuleRecord $record): bool
    {
        return $this->rbacService->canEditRecord($user, $record);
    }

    /**
     * Determine if the user can delete a specific record.
     */
    public function delete(User $user, ModuleRecord $record): bool
    {
        return $this->rbacService->canDeleteRecord($user, $record);
    }

    /**
     * Determine if the user can export records from the module.
     */
    public function export(User $user, Module $module): bool
    {
        return $this->rbacService->canAccessModule($user, $module, 'export');
    }

    /**
     * Determine if the user can import records into the module.
     */
    public function import(User $user, Module $module): bool
    {
        return $this->rbacService->canAccessModule($user, $module, 'import');
    }
}
